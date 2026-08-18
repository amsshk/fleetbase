<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportTariffs extends ImportBaseCommand
{
    protected $signature = 'import:tariffs
        {--file= : Path to the Tariff_Plan XLSX file}
        {--dry-run : Validate and report without writing to the database}';

    protected $description = 'Import tariff/pricing plans from a Tariff_Plan XLSX file into the database';

    protected function defaultFilePattern(): ?string
    {
        return 'Tariff_Plan_*.xlsx';
    }

    protected function importRow(array $row, int $index): mixed
    {
        $name = $this->nullIfEmpty(
            $row['Tariff Name'] ?? $row['tariff_name'] ?? $row['Plan Name'] ?? $row['plan_name'] ?? $row['Name'] ?? $row['name'] ?? null
        );

        if (!$name) {
            $this->line("  Row " . ($index + 2) . ": skipped (no tariff name)");

            return false;
        }

        // Resolve company
        $companyName = $this->nullIfEmpty($row['Company'] ?? $row['company'] ?? $row['Company Name'] ?? null);
        $companyId   = null;

        if ($companyName) {
            $company = DB::table('companies')->where('name', $companyName)->first();
            if ($company) {
                $companyId = $company->uuid;
            }
        }

        // Skip duplicates by name
        $query = DB::table('service_rates')->where('service_name', $name);
        if ($companyId) {
            $query->where('company_uuid', $companyId);
        }
        if ($query->exists()) {
            $this->line("  Row " . ($index + 2) . ": skipped (tariff '{$name}' already exists)");

            return false;
        }

        $uuid   = Str::uuid()->toString();
        $public = 'service_rate_' . Str::random(14);

        DB::table('service_rates')->insert([
            'uuid'              => $uuid,
            'public_id'         => $public,
            'company_uuid'      => $companyId,
            'service_name'      => $name,
            'base_fee'          => $this->nullIfEmpty($row['Base Fee'] ?? $row['base_fee'] ?? $row['Base Price'] ?? null),
            'per_km_fee'        => $this->nullIfEmpty($row['Per KM'] ?? $row['per_km'] ?? $row['Per KM Fee'] ?? null),
            'per_hour_fee'      => $this->nullIfEmpty($row['Per Hour'] ?? $row['per_hour'] ?? $row['Per Hour Fee'] ?? null),
            'currency'          => $this->nullIfEmpty($row['Currency'] ?? $row['currency'] ?? null) ?? 'USD',
            'rate_calculation_method' => $this->nullIfEmpty($row['Calculation Method'] ?? $row['calculation_method'] ?? $row['Method'] ?? null) ?? 'flat',
            'type'              => $this->nullIfEmpty($row['Type'] ?? $row['type'] ?? null),
            'status'            => $this->nullIfEmpty($row['Status'] ?? $row['status'] ?? null) ?? 'active',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $this->line("  Row " . ($index + 2) . ": created tariff '{$name}'");

        return $uuid;
    }
}
