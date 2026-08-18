<?php

namespace App\Console\Commands;

use App\Support\Import\ImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class ImportData extends Command
{
    protected $signature = 'import:data
                            {type : Import type (company, vehicle, user)}
                            {file : Path to CSV/XLSX file}
                            {--dry-run : Validate without writing records}
                            {--rollback-on-error : Roll back full import on first row failure}
                            {--no-report : Skip report file generation}
                            {--json : Output report as JSON}';

    protected $description = 'Import companies, vehicles, or users using Fleetbase model namespaces with validation and reporting.';

    public function __construct(protected ImportService $importService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = strtolower((string) $this->argument('type'));
        $filePath = (string) $this->argument('file');

        if (!File::isAbsolutePath($filePath)) {
            $filePath = base_path($filePath);
        }

        if (!is_file($filePath)) {
            $this->error("Import file not found: {$filePath}");

            return self::FAILURE;
        }

        try {
            $rows = $this->importService->parseFile($filePath);
            $totalRows = count($rows);
            $progressBar = $this->output->createProgressBar($totalRows);
            $progressBar->start();

            $report = $this->importService->importRows(
                $type,
                $rows,
                [
                    'dry_run' => (bool) $this->option('dry-run'),
                    'rollback_on_error' => (bool) $this->option('rollback-on-error'),
                    'write_report' => !(bool) $this->option('no-report'),
                ],
                function () use ($progressBar) {
                    $progressBar->advance();
                }
            );

            $progressBar->finish();
            $this->newLine(2);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Type', 'Model', 'Dry Run', 'Total', 'Successful', 'Failed', 'Report'],
                [[
                    $report['type'],
                    $report['model_class'],
                    $report['dry_run'] ? 'yes' : 'no',
                    $report['total_rows'],
                    $report['successful'],
                    $report['failed'],
                    $report['report_path'] ?? '-',
                ]]
            );

            if (!empty($report['errors'])) {
                $this->newLine();
                $this->warn('Import errors:');
                $this->line(json_encode($report['errors'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }

        return $report['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
