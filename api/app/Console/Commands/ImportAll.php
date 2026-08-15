<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportAll extends Command
{
    protected $signature = 'import:all
        {--companies= : Path to the Company XLSX file}
        {--users= : Path to the Company_Subuser XLSX file}
        {--vehicles= : Path to the Vehicle XLSX file}
        {--branches= : Path to the Branch_Overview XLSX file}
        {--tariffs= : Path to the Tariff_Plan XLSX file}
        {--alerts= : Path to the Alert XLSX file}
        {--dry-run : Validate and report without writing to the database}';

    protected $description = 'Run all import commands in the correct order (companies → users → vehicles → branches → tariffs → alerts)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run') ? ['--dry-run' => true] : [];

        $commands = [
            'import:companies' => ['--file' => $this->option('companies')],
            'import:users'     => ['--file' => $this->option('users')],
            'import:vehicles'  => ['--file' => $this->option('vehicles')],
            'import:branches'  => ['--file' => $this->option('branches')],
            'import:tariffs'   => ['--file' => $this->option('tariffs')],
            'import:alerts'    => ['--file' => $this->option('alerts')],
        ];

        $failed = 0;

        foreach ($commands as $command => $options) {
            $this->info("=== Running: {$command} ===");

            $args = array_filter(array_merge($options, $dryRun), fn ($v) => $v !== null);

            $exitCode = $this->call($command, $args);

            if ($exitCode !== self::SUCCESS) {
                $this->error("  {$command} finished with errors.");
                $failed++;
            }

            $this->newLine();
        }

        if ($failed > 0) {
            $this->warn("{$failed} import(s) finished with errors. Check the logs for details.");

            return self::FAILURE;
        }

        $this->info('All imports completed successfully.');

        return self::SUCCESS;
    }
}
