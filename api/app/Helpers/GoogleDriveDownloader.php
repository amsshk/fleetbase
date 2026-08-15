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
        $html = $this->fetchUrl("https://drive.google.com/drive/folders/{$folderId}");

        $fileIds = $this->extractFileIds($html);

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
        $url = "https://drive.google.com/uc?export=download&id={$fileId}";

        $content = $this->fetchUrl($url, true);

        if (!$content) {
            return null;
        }

        $relativePath = "{$destination}/{$filename}";
        Storage::disk('local')->put($relativePath, $content);

        return storage_path("app/{$relativePath}");
    }

    /**
     * Extract file IDs and names from Google Drive folder HTML.
     */
    private function extractFileIds(string $html): array
    {
        $files = [];

        // Match data-id and aria-label attributes from the folder listing
        if (preg_match_all('/data-id="([^"]+)"[^>]*aria-label="([^"]+)"/', $html, $matches)) {
            foreach ($matches[1] as $i => $id) {
                $name = $matches[2][$i];
                if (!isset($files[$id])) {
                    $files[$id] = $name;
                }
            }
        }

        // Alternative pattern
        if (empty($files) && preg_match_all('/"([a-zA-Z0-9_-]{25,})".*?"([^"]+\.xlsx)"/', $html, $matches)) {
            foreach ($matches[1] as $i => $id) {
                $files[$id] = $matches[2][$i];
            }
        }

        return $files;
    }

    /**
     * Fetch a URL and return its content.
     */
    private function fetchUrl(string $url, bool $followRedirects = false): ?string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/gdrive_cookies.txt');
        curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/gdrive_cookies.txt');
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; FleetbaseImporter/1.0)');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && $response !== false) {
            return $response;
        }

        return null;
    }
}
