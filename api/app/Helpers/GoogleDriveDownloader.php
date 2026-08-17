<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class GoogleDriveDownloader
{
    /**
     * Download all files from a public Google Drive folder.
     *
     * @param  string  $folderId
     * @param  string  $destination  Path relative to storage disk root
     * @return array   List of local file paths that were saved
     */
    public function downloadFolder(string $folderId, string $destination = 'imports'): array
    {
        if (empty($folderId)) {
            return [];
        }

        $html = $this->fetchUrl("https://drive.google.com/drive/folders/{$folderId}", true);

        $fileIds = $this->extractFileIds($html ?? '');

        $saved = [];
        foreach ($fileIds as $id => $name) {
            $local = $this->downloadFile($id, $name, $destination);
            if ($local) {
                $saved[] = $local;
            }
        }

        return $saved;
    }

    /**
     * Download a single file from Google Drive by file ID.
     */
    public function downloadFile(string $fileId, string $filename, string $destination = 'imports'): ?string
    {
        if (empty($fileId) || empty($filename)) {
            return null;
        }

        $url = "https://drive.google.com/uc?export=download&id={$fileId}";

        $content = $this->fetchUrl($url, true);

        if (!$content || $this->looksLikeHtmlInterstitial($content)) {
            return null;
        }

        $relativePath = trim($destination, '/\\') . '/' . ltrim($filename, '/\\');
        Storage::disk('local')->makeDirectory(dirname($relativePath));
        Storage::disk('local')->put($relativePath, $content);

        return storage_path('app/' . ltrim($relativePath, '/\\'));
    }

    /**
     * Extract file IDs and names from Google Drive folder HTML.
     */
    private function extractFileIds(string $html): array
    {
        $files = [];

        if (preg_match_all('/data-id="([^"]+)"[^>]*aria-label="([^"]+)"/i', $html, $matches)) {
            foreach ($matches[1] as $i => $id) {
                $name = trim($matches[2][$i]);
                if ($id !== '' && $name !== '' && !isset($files[$id])) {
                    $files[$id] = $name;
                }
            }
        }

        if (empty($files) && preg_match_all('/(?:\/file\/d\/|id=)([A-Za-z0-9_-]{10,})(?:[\/\?]|"|&)/i', $html, $matches)) {
            foreach ($matches[1] as $id) {
                if (!isset($files[$id])) {
                    $files[$id] = $this->guessFilenameFromUrl($html, $id) ?? "file_{$id}.xlsx";
                }
            }
        }

        if (empty($files) && preg_match_all('/"([a-zA-Z0-9_-]{10,})".*?"([^"]+\.(?:xlsx|xls|csv))"/i', $html, $matches)) {
            foreach ($matches[1] as $i => $id) {
                $name = trim($matches[2][$i]);
                if (!isset($files[$id])) {
                    $files[$id] = $name;
                }
            }
        }

        return $files;
    }

    /**
     * Guess a file name based on the Google Drive HTML around a file ID.
     */
    private function guessFilenameFromUrl(string $html, string $fileId): ?string
    {
        if (preg_match('/"' . preg_quote($fileId, '/') . '"[^\n]{0,200}?"([^"\\r\\n]+\.(?:xlsx|xls|csv))"/i', $html, $match)) {
            return trim($match[1]);
        }

        if (preg_match('/href="[^"]*file\/d\/' . preg_quote($fileId, '/') . '[^\"]*\?[^\"]*"/i', $html, $match)) {
            $href = $match[0];
            if (preg_match('/title="([^"]+)"/i', $href, $titleMatch)) {
                return trim($titleMatch[1]);
            }
        }

        return null;
    }

    /**
     * Detect the Google Drive HTML interstitial page shown for large files.
     */
    private function looksLikeHtmlInterstitial(string $content): bool
    {
        $trimmed = ltrim($content);

        return preg_match('/^\s*(?:<!doctype\s+html|<html\b)/i', $trimmed) === 1;
    }

    /**
     * Fetch a URL and return its content.
     */
    private function fetchUrl(string $url, bool $followRedirects = false): ?string
    {
        $cookieFile = tempnam(sys_get_temp_dir(), 'gdrive_');
        if ($cookieFile === false) {
            $cookieFile = '/tmp/gdrive_cookies.txt';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirects);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; FleetbaseImporter/1.0)');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (is_file($cookieFile)) {
            @unlink($cookieFile);
        }

        if ($httpCode >= 200 && $httpCode < 300 && $response !== false) {
            return $response;
        }

        return null;
    }
}
