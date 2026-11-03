<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            SupplierSeeder::class,
            ItemSeeder::class,
            OrganizationSeeder::class,
            VehicleSeeder::class,
            AcSeeder::class,
            RecruitmentSeeder::class,
            EmployeeSampleSeeder::class,
            SampleTransactionSeeder::class,
        ]);

        if (config('sstock.demo')) {
            $this->call([DemoSeeder::class, DemoPurchaseSeeder::class, DemoPurchaseHistorySeeder::class]);
        }
    }
}
