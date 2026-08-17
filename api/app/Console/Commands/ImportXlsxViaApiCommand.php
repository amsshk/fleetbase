<?php

namespace App\Console\Commands;

use App\Helpers\FleetbaseImporter;
use App\Helpers\GoogleDriveDownloader;
use Illuminate\Console\Command;

class ImportXlsxViaApiCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'import:xlsx-via-api
                            {--dry-run : Preview what would be imported without making API calls}
                            {--type= : Import only a specific type: companies, users, vehicles, branches, tariffs}
                            {--file= : Path to a specific XLSX file to import}
                            {--folder= : Google Drive folder ID to download from}
                            {--api-key= : Fleetbase API key (overrides config)}
                            {--base-url= : Fleetbase API base URL (overrides config)}';

    /**
     * The console command description.
     */
    protected $description = 'Download XLSX files from Google Drive and import them into Fleetbase via API';

    /**
     * Maps XLSX filename keywords to import types.
     */
    protected array $fileTypeMap = [
        'company'    => 'companies',
        'subuser'    => 'users',
        'user'       => 'users',
        'vehicle'    => 'vehicles',
        'branch'     => 'branches',
        'tariff'     => 'tariffs',
        'service'    => 'tariffs',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $type    = $this->normalizeType((string) $this->option('type'));
        $apiKey  = $this->option('api-key') ?: config('import.api_key');
        $baseUrl = $this->option('base-url') ?: config('import.api_host');

        if ($this->option('type') !== null && $this->option('type') !== '' && $type === null) {
            $this->error('Unsupported import type. Valid values: companies, users, vehicles, branches, tariffs.');
            return self::FAILURE;
        }

        if (empty($apiKey)) {
            $this->error('No Fleetbase API key provided. Set FLEETBASE_API_KEY env variable or use --api-key option.');
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode — no data will be sent to the API.');
        }

        // Collect XLSX files
        $files = $this->collectFiles();

        if (empty($files)) {
            $this->error('No XLSX files found. Use --file or --folder to specify files.');
            return self::FAILURE;
        }

        $this->info("Found " . count($files) . " XLSX file(s) to process.");

        $importer = new FleetbaseImporter($apiKey, $baseUrl, $dryRun);
        $totals   = ['created' => 0, 'skipped' => 0, 'errors' => []];

        // Determine import order
        $importOrder = $type ? [$type] : ['companies', 'users', 'vehicles', 'branches', 'tariffs'];

        foreach ($importOrder as $importType) {
            $matchingFiles = $this->matchFilesForType($files, $importType);

            foreach ($matchingFiles as $file) {
                $this->info("Processing [{$importType}]: " . basename($file));

                $rows = $this->readXlsx($file);

                if (empty($rows)) {
                    $this->warn("  No data rows found in " . basename($file));
                    continue;
                }

                $this->line("  Reading " . count($rows) . " rows...");

                $result = match ($importType) {
                    'companies' => $importer->importCompanies($rows),
                    'users'     => $importer->importUsers($rows),
                    'vehicles'  => $importer->importVehicles($rows),
                    'branches'  => $importer->importBranches($rows),
                    'tariffs'   => $importer->importTariffs($rows),
                    default     => ['created' => 0, 'skipped' => 0, 'errors' => ["Unknown type: {$importType}"]],
                };

                $totals['created'] += $result['created'];
                $totals['skipped'] += $result['skipped'];
                $totals['errors']   = array_merge($totals['errors'], $result['errors']);

                $this->line("  ✓ Created: {$result['created']}  Skipped: {$result['skipped']}  Errors: " . count($result['errors']));

                if (!empty($result['errors'])) {
                    foreach (array_slice($result['errors'], 0, 5) as $err) {
                        $this->warn("    ! {$err}");
                    }
                }
            }
        }

        $this->newLine();
        $this->info('Import complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Created', $totals['created']],
                ['Skipped / Duplicates', $totals['skipped']],
                ['Errors', count($totals['errors'])],
            ]
        );

        return empty($totals['errors']) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Collect XLSX files from the specified source.
     */
    protected function collectFiles(): array
    {
        if ($specificFile = $this->option('file')) {
            if (!file_exists($specificFile)) {
                $this->error("File not found: {$specificFile}");
                return [];
            }
            return [$specificFile];
        }

        $folderId = $this->option('folder') ?: config('import.google_drive_folder');

        if ($folderId) {
            $this->info("Downloading files from Google Drive folder: {$folderId}");
            $downloader = new GoogleDriveDownloader();
            $downloaded = $downloader->downloadFolder($folderId);

            if (!empty($downloaded)) {
                $this->info("Downloaded " . count($downloaded) . " file(s).");
                return $downloaded;
            }

            $this->warn('Could not download files from Google Drive. Checking local storage...');
        }

        // Fall back to local storage/imports directory
        $importsDir = storage_path('imports');
        if (!is_dir($importsDir)) {
            return [];
        }

        return glob("{$importsDir}/*.xlsx") ?: [];
    }

    /**
     * Normalise a user-supplied type to one of the canonical import types.
     */
    protected function normalizeType(?string $type): ?string
    {
        if ($type === null || trim($type) === '') {
            return null;
        }

        $normalized = strtolower(trim($type));

        foreach ($this->fileTypeMap as $keyword => $mappedType) {
            if ($normalized === $keyword || $normalized === $mappedType) {
                return $mappedType;
            }
        }

        return null;
    }

    /**
     * Match files to an import type based on filename keywords.
     */
    protected function matchFilesForType(array $files, string $type): array
    {
        $normalizedType = $this->normalizeType($type);
        if ($normalizedType === null) {
            return [];
        }

        // Reverse map: type => keywords
        $keywords = array_keys(array_filter($this->fileTypeMap, fn ($t) => $t === $normalizedType));

        return array_filter($files, function (string $file) use ($keywords, $normalizedType) {
            $lower = strtolower(basename($file));
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return true;
                }
            }

            // If no type filter was set and no keyword matched, skip
            return false;
        });
    }

    /**
     * Read an XLSX file and return rows as associative arrays.
     */
    protected function readXlsx(string $filePath): array
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            $this->error('PhpSpreadsheet is not installed. Run: composer require phpoffice/phpspreadsheet');
            return [];
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $sheet       = $spreadsheet->getActiveSheet();
            $rawRows     = $sheet->toArray(null, true, true, false);

            if (empty($rawRows)) {
                return [];
            }

            $headerIndex = null;
            foreach ($rawRows as $index => $row) {
                $values = array_values($row);
                if (count(array_filter($values, fn ($value) => $value !== null && trim((string) $value) !== '')) > 0) {
                    $headerIndex = $index;
                    break;
                }
            }

            if ($headerIndex === null) {
                return [];
            }

            $headers = array_map(fn ($header) => strtolower(trim((string) $header)), array_values($rawRows[$headerIndex]));
            $rows    = [];

            foreach (array_slice($rawRows, $headerIndex + 1) as $rawRow) {
                $normalizedRow = [];
                foreach (array_values($rawRow) as $key => $value) {
                    $normalizedRow[$headers[$key] ?? "column_{$key}"] = $value;
                }

                if (empty(array_filter($normalizedRow, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                    continue;
                }

                $rows[] = $normalizedRow;
            }

            return $rows;
        } catch (\Exception $e) {
            $this->error("Error reading {$filePath}: " . $e->getMessage());
            return [];
        }
    }
}
