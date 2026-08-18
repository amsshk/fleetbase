<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportAlerts extends ImportBaseCommand
{
    protected $signature = 'import:alerts
        {--file= : Path to the Alert XLSX file}
        {--dry-run : Validate and report without writing to the database}';

    protected $description = 'Import alerts/issues from an Alert XLSX file into the database';

    /**
     * Resolved target table name. Determined once before rows are processed.
     */
    protected string $targetTable = 'alerts';

    protected function defaultFilePattern(): ?string
    {
        return 'Alert_*.xlsx';
    }

    public function handle(): int
    {
        // Determine the correct table once, before any transaction begins.
        $this->targetTable = Schema::hasTable('alerts') ? 'alerts' : 'issues';
        $this->info("Using table: {$this->targetTable}");

        return parent::handle();
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

        if ($this->targetTable === 'alerts') {
            DB::table('alerts')->insert([
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
        } else {
            DB::table('issues')->insert([
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
        }

        $this->line("  Row " . ($index + 2) . ": created alert '{$title}' in '{$this->targetTable}'");

        return $uuid;
    }
}
