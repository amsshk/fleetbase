<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class VehicleImportExampleSeeder extends Seeder
{
    public function run(): void
    {
        $directory = database_path('seeders/examples');
        File::ensureDirectoryExists($directory);

        File::put($directory.'/vehicles.csv', "name,plate_number,vin,make,model,year,company_name\nTruck 1,ABC-123,1M8GDM9AXKP042788,Ford,F-150,2024,Acme Logistics\n");
    }
}
