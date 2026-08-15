<?php

namespace App\Console\Commands;

use App\Support\Import\ImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckDatabaseData extends Command
{
    protected $signature = 'database:check-data {--limit=5 : Number of sample rows to display} {--json : Output results as JSON}';

    protected $description = 'Check available data for Fleetbase companies, vehicles, and users using full model namespaces.';

    public function __construct(protected ImportService $importService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max((int) $this->option('limit'), 0);
        $types = $this->importService->availableTypes();
        $summary = [];

        foreach ($types as $type) {
            $typeConfig = $this->importService->getTypeConfig($type);
            $table = $typeConfig['table'] ?? null;
            $modelClass = $this->importService->resolveModelClass($type);
            $recordCount = null;
            $sampleRows = [];
            $error = null;

            try {
                if ($modelClass !== null) {
                    $recordCount = $modelClass::query()->count();
                    $sampleRows = $limit > 0 ? $modelClass::query()->limit($limit)->get()->toArray() : [];
                } elseif ($table && Schema::hasTable($table)) {
                    $recordCount = DB::table($table)->count();
                    $sampleRows = $limit > 0 ? DB::table($table)->limit($limit)->get()->toArray() : [];
                }
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }

            $summary[] = [
                'type' => $type,
                'table' => $table,
                'model_class' => $modelClass,
                'record_count' => $recordCount,
                'sample_rows' => $sampleRows,
                'error' => $error,
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['Type', 'Table', 'Model Class', 'Records', 'Status'],
            collect($summary)->map(fn (array $row) => [
                $row['type'],
                $row['table'] ?? '-',
                $row['model_class'] ?? 'Not loadable',
                $row['record_count'] ?? 'N/A',
                $row['error'] ? 'Error' : 'OK',
            ])->all()
        );

        foreach ($summary as $row) {
            if (!empty($row['sample_rows'])) {
                $this->newLine();
                $this->info("Sample {$row['type']} rows:");
                $this->line(json_encode($row['sample_rows'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            if ($row['error']) {
                $this->warn("{$row['type']} check error: {$row['error']}");
            }
        }

        return self::SUCCESS;
    }
}
