<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportBranches extends ImportBaseCommand
{
    protected $signature = 'import:branches
        {--file= : Path to the Branch_Overview XLSX file}
        {--dry-run : Validate and report without writing to the database}';

    protected $description = 'Import branches/service areas from a Branch_Overview XLSX file into the database';

    protected function defaultFilePattern(): ?string
    {
        return 'Branch_Overview_*.xlsx';
    }

    protected function importRow(array $row, int $index): mixed
    {
        $name = $this->nullIfEmpty(
            $row['Branch Name'] ?? $row['branch_name'] ?? $row['Name'] ?? $row['name'] ?? null
        );

        if (!$name) {
            $this->line("  Row " . ($index + 2) . ": skipped (no branch name)");

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

        // Skip duplicates by name + company
        $query = DB::table('places')->where('name', $name);
        if ($companyId) {
            $query->where('company_uuid', $companyId);
        }
        if ($query->exists()) {
            $this->line("  Row " . ($index + 2) . ": skipped (branch '{$name}' already exists)");

            return false;
        }

        $uuid   = Str::uuid()->toString();
        $public = 'place_' . Str::random(14);

        DB::table('places')->insert([
            'uuid'         => $uuid,
            'public_id'    => $public,
            'company_uuid' => $companyId,
            'name'         => $name,
            'street1'      => $this->nullIfEmpty($row['Address'] ?? $row['address'] ?? $row['Street'] ?? $row['street'] ?? null),
            'street2'      => $this->nullIfEmpty($row['Address 2'] ?? $row['address2'] ?? null),
            'city'         => $this->nullIfEmpty($row['City'] ?? $row['city'] ?? null),
            'province'     => $this->nullIfEmpty($row['State'] ?? $row['state'] ?? $row['Province'] ?? $row['province'] ?? null),
            'postal_code'  => $this->nullIfEmpty($row['Postal Code'] ?? $row['postal_code'] ?? $row['Zip'] ?? null),
            'country'      => $this->nullIfEmpty($row['Country'] ?? $row['country'] ?? null),
            'phone'        => $this->nullIfEmpty($row['Phone'] ?? $row['phone'] ?? null),
            'type'         => $this->nullIfEmpty($row['Type'] ?? $row['type'] ?? null) ?? 'branch',
            'status'       => $this->nullIfEmpty($row['Status'] ?? $row['status'] ?? null) ?? 'active',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->line("  Row " . ($index + 2) . ": created branch '{$name}'");

        return $uuid;
    }
}
