<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InstallFleetbase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fleetbase:install
                            {--force : Force the operation to run without confirmation}
                            {--skip-migrations : Skip running database migrations}
                            {--skip-seed : Skip seeding the database}
                            {--skip-permissions : Skip creating permissions}
                            {--skip-notify : Skip sending the install notification}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the full Fleetbase installation sequence (migrate, permissions, seed, notify)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->components->info('Starting Fleetbase installation…');

        // Step 1 — Database migrations
        if (!$this->option('skip-migrations')) {
            $this->components->task('Running database migrations', function () {
                $this->call('migrate', ['--force' => true]);
            });
        }

        // Step 2 — Permissions, policies and roles
        if (!$this->option('skip-permissions')) {
            $this->components->task('Creating permissions, policies and roles', function () {
                $this->call('fleetbase:create-permissions');
            });
        }

        // Step 3 — Seeders
        if (!$this->option('skip-seed')) {
            $this->components->task('Seeding the database', function () {
                $this->call('fleetbase:seed');
            });
        }

        // Step 4 — Notify install pages that setup completed
        if (!$this->option('skip-notify')) {
            $this->components->task('Sending install notification', function () {
                $this->call('fleetbase:notify-installed');
            });
        }

        $this->newLine();
        $this->components->info('Fleetbase installation complete.');

        return self::SUCCESS;
    }
}
