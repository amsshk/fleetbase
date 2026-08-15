<?php

namespace App\Support\Import;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class ImportService
{
    public function availableTypes(): array
    {
        return array_keys(config('import.models', []));
    }

    public function getTypeConfig(string $type): array
    {
        $typeConfig = config("import.models.{$type}", []);

        if (empty($typeConfig)) {
            throw new RuntimeException("Unsupported import type [{$type}].");
        }

        return $typeConfig;
    }

    public function resolveModelClass(string $type): ?string
    {
        $typeConfig = $this->getTypeConfig($type);
        $candidates = Arr::wrap($typeConfig['class_candidates'] ?? []);

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function parseFile(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("File does not exist: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, ['csv', 'txt'])) {
            return $this->parseCsv($filePath);
        }

        if ($extension === 'xlsx') {
            return $this->parseXlsx($filePath);
        }

        throw new RuntimeException('Unsupported import file type. Use CSV or XLSX.');
    }

    public function validateRow(array $row, array $requiredFields): array
    {
        $errors = [];

        foreach ($requiredFields as $field) {
            $value = data_get($row, $field);

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '') {
                $errors[] = "Missing required field [{$field}]";
            }
        }

        if (isset($row['email']) && $row['email'] !== '' && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        return $errors;
    }

    public function importFromFile(string $type, string $filePath, array $options = [], ?callable $progress = null): array
    {
        $rows = $this->parseFile($filePath);

        return $this->importRows($type, $rows, $options, $progress);
    }

    public function importRows(string $type, array $rows, array $options = [], ?callable $progress = null): array
    {
        $type = strtolower($type);
        $typeConfig = $this->getTypeConfig($type);
        $modelClass = $this->resolveModelClass($type);

        if ($modelClass === null) {
            throw new RuntimeException("No loadable model found for import type [{$type}].");
        }

        $dryRun = (bool) ($options['dry_run'] ?? false);
        $rollbackOnError = (bool) ($options['rollback_on_error'] ?? false);
        $writeReport = (bool) ($options['write_report'] ?? true);
        $requiredFields = Arr::wrap($typeConfig['required'] ?? []);

        $report = [
            'type' => $type,
            'model_class' => $modelClass,
            'dry_run' => $dryRun,
            'rollback_on_error' => $rollbackOnError,
            'total_rows' => count($rows),
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'report_path' => null,
        ];

        $companyModelClass = $this->resolveModelClass('company');

        $importer = function () use (
            &$report,
            $rows,
            $requiredFields,
            $progress,
            $rollbackOnError,
            $dryRun,
            $type,
            $typeConfig,
            $modelClass,
            $companyModelClass
        ) {
            $totalRows = count($rows);

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 1;
                $errors = $this->validateRow($row, $requiredFields);

                if (!empty($errors)) {
                    $report['failed']++;
                    $report['errors'][] = [
                        'row' => $rowNumber,
                        'errors' => $errors,
                    ];

                    if ($rollbackOnError && !$dryRun) {
                        throw new RuntimeException("Validation failed on row {$rowNumber}");
                    }

                    if (is_callable($progress)) {
                        $progress($rowNumber, $totalRows, $row, $report);
                    }

                    continue;
                }

                $payload = $this->preparePayload($type, $row, $typeConfig, $companyModelClass);

                if ($dryRun) {
                    $report['successful']++;

                    if (is_callable($progress)) {
                        $progress($rowNumber, $totalRows, $row, $report);
                    }

                    continue;
                }

                try {
                    $this->persistRow($modelClass, $payload, Arr::wrap($typeConfig['unique_by'] ?? []));
                    $report['successful']++;
                } catch (Throwable $exception) {
                    $report['failed']++;
                    $report['errors'][] = [
                        'row' => $rowNumber,
                        'errors' => [$exception->getMessage()],
                    ];

                    if ($rollbackOnError) {
                        throw $exception;
                    }
                }

                if (is_callable($progress)) {
                    $progress($rowNumber, $totalRows, $row, $report);
                }
            }
        };

        if ($dryRun || !$rollbackOnError) {
            $importer();
        } else {
            DB::transaction($importer);
        }

        $report['finished_at'] = now()->toIso8601String();

        if ($writeReport) {
            $report['report_path'] = $this->writeReport($report);
        }

        return $report;
    }

    protected function preparePayload(string $type, array $row, array $typeConfig, ?string $companyModelClass): array
    {
        $allowedFields = array_unique(
            array_merge(
                Arr::wrap($typeConfig['required'] ?? []),
                Arr::wrap($typeConfig['optional'] ?? []),
                Arr::wrap($typeConfig['unique_by'] ?? [])
            )
        );

        $payload = Arr::only($row, $allowedFields);

        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $payload[$key] = trim($value);
            }
        }

        if ($type === 'user' && !empty($payload['password'])) {
            $passwordInfo = password_get_info($payload['password']);

            if (($passwordInfo['algoName'] ?? 'unknown') === 'unknown') {
                $payload['password'] = Hash::make($payload['password']);
            }
        }

        $companyRelation = data_get($typeConfig, 'relations.company', []);

        if (!empty($companyRelation) && $companyModelClass !== null) {
            $payload = $this->applyCompanyRelation($payload, $companyRelation, $companyModelClass);
        }

        return $payload;
    }

    protected function applyCompanyRelation(array $payload, array $relationConfig, string $companyModelClass): array
    {
        $targetColumn = data_get($relationConfig, 'target_column', 'company_uuid');
        $sourceFields = Arr::wrap(data_get($relationConfig, 'source_fields', ['company_uuid', 'company_name']));

        $rawValue = null;
        $lookupField = null;

        foreach ($sourceFields as $field) {
            $value = data_get($payload, $field);

            if ($value !== null && $value !== '') {
                $rawValue = $value;
                $lookupField = $field;
                break;
            }
        }

        if ($rawValue === null) {
            return $payload;
        }

        if ($lookupField === 'company_uuid') {
            $payload[$targetColumn] = $rawValue;

            return $payload;
        }

        try {
            $company = $companyModelClass::query()->where('name', $rawValue)->first();

            if ($company && isset($company->uuid)) {
                $payload[$targetColumn] = $company->uuid;
            }
        } catch (Throwable $exception) {
        }

        return $payload;
    }

    protected function persistRow(string $modelClass, array $payload, array $uniqueBy): void
    {
        $lookup = [];

        foreach ($uniqueBy as $uniqueField) {
            if (isset($payload[$uniqueField]) && $payload[$uniqueField] !== '') {
                $lookup[$uniqueField] = $payload[$uniqueField];
            }
        }

        if (empty($lookup)) {
            throw new RuntimeException('Unable to determine unique row identifier for import.');
        }

        $model = $modelClass::firstOrNew($lookup);
        $model->forceFill($payload);
        $model->save();
    }

    protected function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to open file [{$filePath}]");
        }

        $headers = [];
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if (empty($headers)) {
                $headers = array_map([$this, 'normalizeHeader'], $data);
                continue;
            }

            if (count(array_filter($data, fn ($value) => $value !== null && $value !== '')) === 0) {
                continue;
            }

            $values = array_slice(array_pad($data, count($headers), null), 0, count($headers));
            $rows[] = array_combine($headers, $values);
        }

        fclose($handle);

        return $rows;
    }

    protected function parseXlsx(string $filePath): array
    {
        if (!class_exists(IOFactory::class)) {
            throw new RuntimeException('XLSX import requires phpoffice/phpspreadsheet.');
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);

        if (empty($data)) {
            return [];
        }

        $headers = array_map([$this, 'normalizeHeader'], array_shift($data));
        $rows = [];

        foreach ($data as $row) {
            if (count(array_filter($row, fn ($value) => $value !== null && $value !== '')) === 0) {
                continue;
            }

            $values = array_slice(array_pad($row, count($headers), null), 0, count($headers));
            $rows[] = array_combine($headers, $values);
        }

        return $rows;
    }

    protected function normalizeHeader(?string $header): string
    {
        return Str::of((string) $header)
            ->trim()
            ->lower()
            ->replace(['-', ' '], '_')
            ->__toString();
    }

    protected function writeReport(array $report): string
    {
        $disk = config('import.reports_disk', 'local');
        $directory = trim(config('import.reports_path', 'import/reports'), '/');
        $fileName = sprintf('%s-%s.json', $report['type'], now()->format('YmdHis'));
        $path = $directory.'/'.$fileName;

        Storage::disk($disk)->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
