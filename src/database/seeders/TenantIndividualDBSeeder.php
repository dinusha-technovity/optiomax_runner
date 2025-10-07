<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TenantIndividualDBSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(TenantRoleSeeder::class); 
        $this->call(TenantRoleHasPermissionsSeeder::class);
        $this->call(TenantModelHasRolesSeeder::class);
        $this->call(TenantDesignationSeeder::class);
        $this->call(AssetMaintainScheduleParametersSeeder::class);
        $this->call(MaintenanceTasksTypeSeeder::class);
        $this->call(CurrenciesSeeder::class);
        $this->call(ItemCategoriesSeeder::class);
        $this->call(ItemTypesSeeder::class);
        $this->call(MeasurementsSeeder::class);
        $this->call(ItemSupplierSeeder::class);
    }
}
