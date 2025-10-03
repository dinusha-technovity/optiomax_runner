<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetAvailabilityTermTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('asset_availability_term_types')->insert([
            [
                'name' => 'Long Term Lease',
                'description' => 'Asset available for long term lease',
                'isactive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Short Term Hire',
                'description' => 'Asset available for short term hire',
                'isactive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Internal Use',
                'description' => 'Asset available for internal use only',
                'isactive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Sale',
                'description' => 'Asset available for sale',
                'isactive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Maintenance',
                'description' => 'Asset currently under maintenance',
                'isactive' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Reserved',
                'description' => 'Asset reserved for future use',
                'isactive' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Out of Service',
                'description' => 'Asset is out of service',
                'isactive' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Demo',
                'description' => 'Asset available for demonstration purposes',
                'isactive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Trial',
                'description' => 'Asset available for trial use',
                'isactive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Shared Use',
                'description' => 'Asset available for shared use',
                'isactive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Temporary Storage',
                'description' => 'Asset used for temporary storage',
                'isactive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
