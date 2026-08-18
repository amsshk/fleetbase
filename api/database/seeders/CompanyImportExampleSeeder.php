<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CompanyImportExampleSeeder extends Seeder
{
    public function run(): void
    {
        $directory = database_path('seeders/examples');
        File::ensureDirectoryExists($directory);

        File::put($directory.'/companies.csv', "name,email,phone,country,currency\nAcme Logistics,ops@acme.test,+1-555-0100,US,USD\n");
    }
}
