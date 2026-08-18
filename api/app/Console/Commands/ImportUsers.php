<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportUsers extends ImportBaseCommand
{
    protected $signature = 'import:users
        {--file= : Path to the Company_Subuser XLSX file}
        {--dry-run : Validate and report without writing to the database}';

    protected $description = 'Import company sub-users from a Company_Subuser XLSX file into the database';

    protected function defaultFilePattern(): ?string
    {
        return 'Company_Subuser_*.xlsx';
    }

    protected function importRow(array $row, int $index): mixed
    {
        $email = $this->nullIfEmpty($row['Email'] ?? $row['email'] ?? null);
        $name  = $this->nullIfEmpty($row['Name'] ?? $row['name'] ?? $row['Full Name'] ?? $row['full_name'] ?? null);

        if (!$email && !$name) {
            $this->line("  Row " . ($index + 2) . ": skipped (no email or name)");

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

        // Skip duplicate users by email
        if ($email) {
            $existing = DB::table('users')->where('email', $email)->first();
            if ($existing) {
                $this->line("  Row " . ($index + 2) . ": skipped (user '{$email}' already exists)");

                return false;
            }
        }

        $uuid      = Str::uuid()->toString();
        $public    = 'user_' . Str::random(14);
        $password  = $this->nullIfEmpty($row['Password'] ?? null);

        DB::table('users')->insert([
            'uuid'         => $uuid,
            'public_id'    => $public,
            'company_uuid' => $companyId,
            'name'         => $name,
            'email'        => $email,
            'phone'        => $this->nullIfEmpty($row['Phone'] ?? $row['phone'] ?? null),
            'type'         => $this->nullIfEmpty($row['Type'] ?? $row['type'] ?? $row['Role'] ?? $row['role'] ?? null) ?? 'user',
            'status'       => $this->nullIfEmpty($row['Status'] ?? $row['status'] ?? null) ?? 'active',
            'password'     => $password ? Hash::make($password) : Hash::make(Str::random(16)),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Link user to company via company_users table if it exists
        if ($companyId) {
            try {
                DB::table('company_users')->insert([
                    'uuid'         => Str::uuid()->toString(),
                    'company_uuid' => $companyId,
                    'user_uuid'    => $uuid,
                    'status'       => 'active',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            } catch (\Throwable $e) {
                // Table may not exist or may have different schema — log and continue
                \Illuminate\Support\Facades\Log::debug('company_users insert skipped: ' . $e->getMessage());
            }
        }

        $this->line("  Row " . ($index + 2) . ": created user '{$email}'");

        return $uuid;
    }
}
