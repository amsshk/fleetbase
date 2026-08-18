<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportCompanies extends ImportBaseCommand
{
    protected $signature = 'import:companies
        {--file= : Path to the Company XLSX file}
        {--dry-run : Validate and report without writing to the database}';

    protected $description = 'Import companies from a Company XLSX file into the database';

    protected function defaultFilePattern(): ?string
    {
        return 'Company_*.xlsx';
    }

    protected function importRow(array $row, int $index): mixed
    {
        $name = $this->nullIfEmpty($row['Company Name'] ?? $row['name'] ?? $row['Name'] ?? null);

        if (!$name) {
            $this->line("  Row " . ($index + 2) . ": skipped (no company name)");

            return false;
        }

        $slug = $this->nullIfEmpty($row['Slug'] ?? $row['slug'] ?? null) ?? Str::slug($name);

        // Check for duplicate by name
        $existing = DB::table('companies')->where('name', $name)->first();
        if ($existing) {
            $this->line("  Row " . ($index + 2) . ": skipped (company '{$name}' already exists)");

            return false;
        }

        $uuid   = Str::uuid()->toString();
        $public = 'company_' . Str::random(14);

        DB::table('companies')->insert([
            'uuid'         => $uuid,
            'public_id'    => $public,
            'name'         => $name,
            'slug'         => $slug,
            'email'        => $this->nullIfEmpty($row['Email'] ?? $row['email'] ?? null),
            'phone'        => $this->nullIfEmpty($row['Phone'] ?? $row['phone'] ?? null),
            'website'      => $this->nullIfEmpty($row['Website'] ?? $row['website'] ?? null),
            'description'  => $this->nullIfEmpty($row['Description'] ?? $row['description'] ?? null),
            'country'      => $this->nullIfEmpty($row['Country'] ?? $row['country'] ?? null),
            'currency'     => $this->nullIfEmpty($row['Currency'] ?? $row['currency'] ?? null),
            'timezone'     => $this->nullIfEmpty($row['Timezone'] ?? $row['timezone'] ?? null),
            'type'         => $this->nullIfEmpty($row['Type'] ?? $row['type'] ?? null),
            'status'       => $this->nullIfEmpty($row['Status'] ?? $row['status'] ?? null) ?? 'active',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->line("  Row " . ($index + 2) . ": created company '{$name}'");

        return $uuid;
    }
}
