<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class UserImportExampleSeeder extends Seeder
{
    public function run(): void
    {
        $directory = database_path('seeders/examples');
        File::ensureDirectoryExists($directory);

        File::put($directory.'/users.csv', "name,email,password,company_name\nImport User,import.user@example.test,CHANGE_ME_PASSWORD,Acme Logistics\n");
    }
}
