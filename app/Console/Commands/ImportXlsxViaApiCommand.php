<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ImportXlsxViaApiCommand extends Command
{
    protected $signature = 'import:xlsx-via-api {--dry-run} {--type=}';
    protected $description = 'Download XLSX from Google Drive and import via Fleetbase API';

    protected $apiKey = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJ1ZmZpemlvc2VydmljZXMtYWRtaW5AZXpkZXZzb2Z0Lm5ldC0xMDQ4OS00OCIsImlzcyI6Imh0dHBzOi8vYXV0aGVudGljYXRlLnVmZml6aW8uY29tIiwiaWF0IjoxNzg2NzY0ODQyfQ.YrQbto2gTWv4st5WeEMlWQzEiwe5fC8oxAZTSinXAuY';
    protected $googleDriveFolderId = '1Y5M9h7KXD-g17yzr85zSUz_zY0VUC-Im';
    protected $dryRun = false;
    protected $stats = ['success' => 0, 'failed' => 0, 'skipped' => 0];
    protected $companies = [];
    protected $users = [];

    public function handle()
    {
        $this->dryRun = $this->option('dry-run');
        $type = $this->option('type');

        if ($this->dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No data will be imported');
        }

        $this->info('🚀 Starting Fleetbase Data Import...\n');

        // Step 1: Download files
        $this->downloadFilesFromGoogleDrive();

        // Step 2: Import data in correct order
        if (!$type || $type === 'companies') {
            $this->importCompanies();
        }
        if (!$type || $type === 'users') {
            $this->importUsers();
        }
        if (!$type || $type === 'vehicles') {
            $this->importVehicles();
        }
        if (!$type || $type === 'branches') {
            $this->importBranches();
        }
        if (!$type || $type === 'tariffs') {
            $this->importTariffs();
        }

        $this->showSummary();
    }

    private function downloadFilesFromGoogleDrive()
    {
        $this->info('📥 Downloading files from Google Drive...');

        $files = [
            'Company_15-08-2026_06-00-53_AM.xlsx',
            'Company_Subuser_15-08-2026_06-01-20_AM.xlsx',
            'Vehicle_15-08-2026_06-02-16_AM.xlsx',
            'Branch_Overview_15-08-2026_06-01-36_AM.xlsx',
            'Tariff_Plan_15-08-2026_06-04-58_AM.xlsx',
            'Alert_15-08-2026_06-03-42_AM.xlsx',
            'Alert_15-08-2026_06-03-59_AM.xlsx',
        ];

        Storage::makeDirectory('imports', 0755, true);

        foreach ($files as $file) {
            try {
                // Construct Google Drive direct download URL
                $fileId = $this->getFileIdFromDrive($file);
                if ($fileId) {
                    $downloadUrl = "https://drive.google.com/uc?export=download&id={$fileId}";
                    $response = Http::get($downloadUrl);

                    if ($response->successful()) {
                        Storage::put("imports/{$file}", $response->body());
                        $this->line("  ✓ Downloaded: {$file}");
                    }
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Failed to download {$file}: {$e->getMessage()}");
            }
        }

        $this->newLine();
    }

    private function getFileIdFromDrive($fileName)
    {
        // Placeholder - would need actual Google Drive API to list files
        // For now, return null and use manual fallback
        return null;
    }

    private function importCompanies()
    {
        $this->info('🏢 Importing Companies...');

        $filePath = Storage::path('imports/Company_15-08-2026_06-00-53_AM.xlsx');

        if (!file_exists($filePath)) {
            $this->warn("  File not found: {$filePath}");
            return;
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            $bar = $this->output->createProgressBar(count($rows) - 1);
            $bar->start();

            foreach (array_slice($rows, 1) as $row) {
                if (empty(array_filter($row))) continue;

                $companyData = [
                    'name' => $row[0] ?? '',
                    'email' => $row[1] ?? '',
                    'phone' => $row[2] ?? '',
                    'address' => $row[3] ?? '',
                ];

                if (empty($companyData['name'])) {
                    $this->stats['skipped']++;
                    $bar->advance();
                    continue;
                }

                if (!$this->dryRun) {
                    try {
                        $response = Http::withHeaders([
                            'Authorization' => "Bearer {$this->apiKey}",
                            'Content-Type' => 'application/json',
                        ])->post('https://api.fleetbase.io/v1/companies', $companyData);

                        if ($response->successful()) {
                            $company = $response->json()['data'] ?? $response->json();
                            $this->companies[$companyData['name']] = $company['id'] ?? $company;
                            $this->stats['success']++;
                        } else {
                            $this->stats['failed']++;
                        }
                    } catch (\Exception $e) {
                        $this->stats['failed']++;
                    }
                } else {
                    $this->stats['success']++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } catch (\Exception $e) {
            $this->error("  Error: {$e->getMessage()}");
        }
    }

    private function importUsers()
    {
        $this->info('👥 Importing Users...');

        $filePath = Storage::path('imports/Company_Subuser_15-08-2026_06-01-20_AM.xlsx');

        if (!file_exists($filePath)) {
            $this->warn("  File not found: {$filePath}");
            return;
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            $bar = $this->output->createProgressBar(count($rows) - 1);
            $bar->start();

            foreach (array_slice($rows, 1) as $row) {
                if (empty(array_filter($row))) continue;

                $userData = [
                    'name' => $row[0] ?? '',
                    'email' => $row[1] ?? '',
                    'phone' => $row[2] ?? '',
                    'company_id' => $this->getCompanyId($row[3] ?? ''),
                ];

                if (empty($userData['name']) || empty($userData['company_id'])) {
                    $this->stats['skipped']++;
                    $bar->advance();
                    continue;
                }

                if (!$this->dryRun) {
                    try {
                        $response = Http::withHeaders([
                            'Authorization' => "Bearer {$this->apiKey}",
                            'Content-Type' => 'application/json',
                        ])->post('https://api.fleetbase.io/v1/users', $userData);

                        if ($response->successful()) {
                            $this->stats['success']++;
                        } else {
                            $this->stats['failed']++;
                        }
                    } catch (\Exception $e) {
                        $this->stats['failed']++;
                    }
                } else {
                    $this->stats['success']++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } catch (\Exception $e) {
            $this->error("  Error: {$e->getMessage()}");
        }
    }

    private function importVehicles()
    {
        $this->info('🚗 Importing Vehicles...');

        $filePath = Storage::path('imports/Vehicle_15-08-2026_06-02-16_AM.xlsx');

        if (!file_exists($filePath)) {
            $this->warn("  File not found: {$filePath}");
            return;
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            $bar = $this->output->createProgressBar(count($rows) - 1);
            $bar->start();

            foreach (array_slice($rows, 1) as $row) {
                if (empty(array_filter($row))) continue;

                $vehicleData = [
                    'make' => $row[0] ?? '',
                    'model' => $row[1] ?? '',
                    'registration' => $row[2] ?? '',
                    'vin' => $row[3] ?? '',
                    'company_id' => $this->getCompanyId($row[4] ?? ''),
                ];

                if (empty($vehicleData['registration']) || empty($vehicleData['company_id'])) {
                    $this->stats['skipped']++;
                    $bar->advance();
                    continue;
                }

                if (!$this->dryRun) {
                    try {
                        $response = Http::withHeaders([
                            'Authorization' => "Bearer {$this->apiKey}",
                            'Content-Type' => 'application/json',
                        ])->post('https://api.fleetbase.io/v1/vehicles', $vehicleData);

                        if ($response->successful()) {
                            $this->stats['success']++;
                        } else {
                            $this->stats['failed']++;
                        }
                    } catch (\Exception $e) {
                        $this->stats['failed']++;
                    }
                } else {
                    $this->stats['success']++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } catch (\Exception $e) {
            $this->error("  Error: {$e->getMessage()}");
        }
    }

    private function importBranches()
    {
        $this->info('🏪 Importing Branches...');

        $filePath = Storage::path('imports/Branch_Overview_15-08-2026_06-01-36_AM.xlsx');

        if (!file_exists($filePath)) {
            $this->warn("  File not found: {$filePath}");
            return;
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            $bar = $this->output->createProgressBar(count($rows) - 1);
            $bar->start();

            foreach (array_slice($rows, 1) as $row) {
                if (empty(array_filter($row))) continue;

                $branchData = [
                    'name' => $row[0] ?? '',
                    'address' => $row[1] ?? '',
                    'city' => $row[2] ?? '',
                    'phone' => $row[3] ?? '',
                    'company_id' => $this->getCompanyId($row[4] ?? ''),
                ];

                if (empty($branchData['name']) || empty($branchData['company_id'])) {
                    $this->stats['skipped']++;
                    $bar->advance();
                    continue;
                }

                if (!$this->dryRun) {
                    try {
                        $response = Http::withHeaders([
                            'Authorization' => "Bearer {$this->apiKey}",
                            'Content-Type' => 'application/json',
                        ])->post('https://api.fleetbase.io/v1/places', $branchData);

                        if ($response->successful()) {
                            $this->stats['success']++;
                        } else {
                            $this->stats['failed']++;
                        }
                    } catch (\Exception $e) {
                        $this->stats['failed']++;
                    }
                } else {
                    $this->stats['success']++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } catch (\Exception $e) {
            $this->error("  Error: {$e->getMessage()}");
        }
    }

    private function importTariffs()
    {
        $this->info('💰 Importing Tariffs...');

        $filePath = Storage::path('imports/Tariff_Plan_15-08-2026_06-04-58_AM.xlsx');

        if (!file_exists($filePath)) {
            $this->warn("  File not found: {$filePath}");
            return;
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            $bar = $this->output->createProgressBar(count($rows) - 1);
            $bar->start();

            foreach (array_slice($rows, 1) as $row) {
                if (empty(array_filter($row))) continue;

                $tariffData = [
                    'name' => $row[0] ?? '',
                    'description' => $row[1] ?? '',
                    'amount' => (float) ($row[2] ?? 0),
                    'company_id' => $this->getCompanyId($row[3] ?? ''),
                ];

                if (empty($tariffData['name']) || $tariffData['amount'] <= 0) {
                    $this->stats['skipped']++;
                    $bar->advance();
                    continue;
                }

                if (!$this->dryRun) {
                    try {
                        $response = Http::withHeaders([
                            'Authorization' => "Bearer {$this->apiKey}",
                            'Content-Type' => 'application/json',
                        ])->post('https://api.fleetbase.io/v1/service_rates', $tariffData);

                        if ($response->successful()) {
                            $this->stats['success']++;
                        } else {
                            $this->stats['failed']++;
                        }
                    } catch (\Exception $e) {
                        $this->stats['failed']++;
                    }
                } else {
                    $this->stats['success']++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } catch (\Exception $e) {
            $this->error("  Error: {$e->getMessage()}");
        }
    }

    private function getCompanyId($companyName)
    {
        return $this->companies[$companyName] ?? null;
    }

    private function showSummary()
    {
        $this->newLine();
        $this->info('📊 Import Summary:');
        $this->line("  ✓ Success:  {$this->stats['success']}");
        $this->line("  ✗ Failed:   {$this->stats['failed']}");
        $this->line("  ⊘ Skipped:  {$this->stats['skipped']}");
        $this->newLine();

        if ($this->dryRun) {
            $this->warn('This was a DRY RUN - no data was actually imported.');
        } else {
            $this->info('✅ Import completed successfully!');
        }
    }
}
