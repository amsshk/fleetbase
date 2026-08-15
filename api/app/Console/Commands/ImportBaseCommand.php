<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

abstract class ImportBaseCommand extends Command
{
    /**
     * Default import storage path.
     */
    protected string $importPath = '/var/www/fleetbase/storage/imports/';

    /**
     * Import statistics.
     */
    protected array $stats = [
        'processed' => 0,
        'created'   => 0,
        'skipped'   => 0,
        'errors'    => 0,
    ];

    /**
     * Run the import inside a database transaction (unless --dry-run).
     */
    public function handle(): int
    {
        $file = $this->resolveFile();

        if (!$file) {
            $this->error('No import file found. Pass --file=<path> or place the file in ' . $this->importPath);

            return self::FAILURE;
        }

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $this->info("Reading: {$file}");
        $rows = $this->readXlsx($file);

        if (empty($rows)) {
            $this->warn('No rows found in file.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY RUN] No data will be written to the database.');
        }

        try {
            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                $this->stats['processed']++;
                try {
                    $result = $this->importRow($row, $index);
                    if ($result === false) {
                        $this->stats['skipped']++;
                    } else {
                        $this->stats['created']++;
                    }
                } catch (\Throwable $e) {
                    $this->stats['errors']++;
                    $this->warn("  Row " . ($index + 2) . " error: " . $e->getMessage());
                    Log::warning('Import row error', ['command' => static::class, 'row' => $index + 2, 'error' => $e->getMessage()]);
                }
            }

            if ($dryRun) {
                DB::rollBack();
                $this->info('[DRY RUN] Transaction rolled back — no changes persisted.');
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Import failed: ' . $e->getMessage());
            Log::error('Import failed', ['command' => static::class, 'error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $this->printReport();

        return self::SUCCESS;
    }

    /**
     * Import a single row. Return false to indicate a skipped row.
     */
    abstract protected function importRow(array $row, int $index): mixed;

    /**
     * Read an XLSX file and return rows as associative arrays keyed by header.
     */
    protected function readXlsx(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, false);

        if (count($rows) < 2) {
            return [];
        }

        $headers = array_map(fn ($h) => trim((string) $h), array_shift($rows));

        $result = [];
        foreach ($rows as $row) {
            $assoc = array_combine($headers, array_pad($row, count($headers), null));
            // Skip completely empty rows
            if (empty(array_filter($assoc, fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }
            $result[] = $assoc;
        }

        return $result;
    }

    /**
     * Resolve the path to the XLSX file.
     */
    protected function resolveFile(): ?string
    {
        if ($this->hasOption('file') && $this->option('file')) {
            $path = $this->option('file');

            return str_starts_with($path, '/') ? $path : base_path($path);
        }

        // Auto-detect by pattern in default import directory
        $pattern = $this->defaultFilePattern();
        if ($pattern) {
            $files = glob($this->importPath . $pattern);
            if (!empty($files)) {
                return $files[0];
            }
        }

        return null;
    }

    /**
     * Glob pattern used to auto-detect the file in the import directory.
     * Override in subclasses.
     */
    protected function defaultFilePattern(): ?string
    {
        return null;
    }

    /**
     * Print a summary report.
     */
    protected function printReport(): void
    {
        $this->newLine();
        $this->info('--- Import Report ---');
        $this->line("  Processed : {$this->stats['processed']}");
        $this->line("  Created   : {$this->stats['created']}");
        $this->line("  Skipped   : {$this->stats['skipped']}");
        $this->line("  Errors    : {$this->stats['errors']}");
        $this->newLine();

        $logEntry = json_encode(array_merge(['command' => static::class, 'time' => now()->toIso8601String()], $this->stats));
        Log::channel('single')->info($logEntry);
    }

    /**
     * Normalize a value to null when empty.
     */
    protected function nullIfEmpty(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }
}
