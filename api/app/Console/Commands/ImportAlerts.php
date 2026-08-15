<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportAlerts extends ImportBaseCommand
{
    protected $signature = 'import:alerts
        {--file= : Path to the Alert XLSX file}
        {--dry-run : Validate and report without writing to the database}';

    protected $description = 'Import alerts/issues from an Alert XLSX file into the database';

    protected function defaultFilePattern(): ?string
    {
        return 'Alert_*.xlsx';
    }

    protected function importRow(array $row, int $index): mixed
    {
        $title = $this->nullIfEmpty(
            $row['Title'] ?? $row['title'] ?? $row['Alert'] ?? $row['alert'] ?? $row['Name'] ?? $row['name'] ?? null
        );

        if (!$title) {
            $this->line("  Row " . ($index + 2) . ": skipped (no alert title)");

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

        $uuid   = Str::uuid()->toString();
        $public = 'alert_' . Str::random(14);

        // Try inserting into `alerts` table first, fall back to `issues`
        $table = 'alerts';
        try {
            DB::table($table)->insert([
                'uuid'         => $uuid,
                'public_id'    => $public,
                'company_uuid' => $companyId,
                'title'        => $title,
                'message'      => $this->nullIfEmpty($row['Message'] ?? $row['message'] ?? $row['Description'] ?? $row['description'] ?? null),
                'type'         => $this->nullIfEmpty($row['Type'] ?? $row['type'] ?? null) ?? 'info',
                'severity'     => $this->nullIfEmpty($row['Severity'] ?? $row['severity'] ?? null) ?? 'low',
                'status'       => $this->nullIfEmpty($row['Status'] ?? $row['status'] ?? null) ?? 'open',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            // Try `issues` table as fallback
            try {
                $table = 'issues';
                DB::table($table)->insert([
                    'uuid'         => $uuid,
                    'public_id'    => $public,
                    'company_uuid' => $companyId,
                    'title'        => $title,
                    'report'       => $this->nullIfEmpty($row['Message'] ?? $row['message'] ?? $row['Description'] ?? null),
                    'type'         => $this->nullIfEmpty($row['Type'] ?? $row['type'] ?? null) ?? 'other',
                    'priority'     => $this->nullIfEmpty($row['Severity'] ?? $row['severity'] ?? $row['Priority'] ?? null) ?? 'low',
                    'status'       => $this->nullIfEmpty($row['Status'] ?? $row['status'] ?? null) ?? 'pending',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            } catch (\Throwable $e2) {
                throw new \RuntimeException("Could not insert alert into 'alerts' or 'issues' table: " . $e2->getMessage());
            }
        }

        $this->line("  Row " . ($index + 2) . ": created alert '{$title}' in '{$table}'");

        return $uuid;
    }
}
