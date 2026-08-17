<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupFleetbase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fleetbase:setup
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
    protected $description = 'Alias for fleetbase:install — run the full Fleetbase setup sequence';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        return $this->call('fleetbase:install', [
            '--force'             => $this->option('force'),
            '--skip-migrations'   => $this->option('skip-migrations'),
            '--skip-seed'         => $this->option('skip-seed'),
            '--skip-permissions'  => $this->option('skip-permissions'),
            '--skip-notify'       => $this->option('skip-notify'),
        ]);
    }
}
