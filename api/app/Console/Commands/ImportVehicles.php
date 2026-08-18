<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportVehicles extends ImportBaseCommand
{
    protected $signature = 'import:vehicles
        {--file= : Path to the Vehicle XLSX file}
        {--dry-run : Validate and report without writing to the database}';

    protected $description = 'Import vehicles from a Vehicle XLSX file into the database';

    protected function defaultFilePattern(): ?string
    {
        return 'Vehicle_*.xlsx';
    }

    protected function importRow(array $row, int $index): mixed
    {
        $plateNumber = $this->nullIfEmpty(
            $row['Plate Number'] ?? $row['plate_number'] ?? $row['Registration'] ?? $row['registration'] ?? null
        );

        $name = $this->nullIfEmpty($row['Name'] ?? $row['name'] ?? $row['Vehicle Name'] ?? null);

        if (!$plateNumber && !$name) {
            $this->line("  Row " . ($index + 2) . ": skipped (no plate number or vehicle name)");

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

        // Skip duplicates by plate number
        if ($plateNumber) {
            $existing = DB::table('vehicles')->where('plate_number', $plateNumber)->first();
            if ($existing) {
                $this->line("  Row " . ($index + 2) . ": skipped (vehicle '{$plateNumber}' already exists)");

                return false;
            }
        }

        $uuid   = Str::uuid()->toString();
        $public = 'vehicle_' . Str::random(14);

        DB::table('vehicles')->insert([
            'uuid'          => $uuid,
            'public_id'     => $public,
            'company_uuid'  => $companyId,
            'name'          => $name,
            'plate_number'  => $plateNumber,
            'make'          => $this->nullIfEmpty($row['Make'] ?? $row['make'] ?? null),
            'model'         => $this->nullIfEmpty($row['Model'] ?? $row['model'] ?? null),
            'year'          => $this->nullIfEmpty($row['Year'] ?? $row['year'] ?? null),
            'color'         => $this->nullIfEmpty($row['Color'] ?? $row['color'] ?? null),
            'vin'           => $this->nullIfEmpty($row['VIN'] ?? $row['vin'] ?? null),
            'type'          => $this->nullIfEmpty($row['Type'] ?? $row['type'] ?? null),
            'status'        => $this->nullIfEmpty($row['Status'] ?? $row['status'] ?? null) ?? 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $identifier = $plateNumber ?? $name;
        $this->line("  Row " . ($index + 2) . ": created vehicle '{$identifier}'");

        return $uuid;
    }
}
