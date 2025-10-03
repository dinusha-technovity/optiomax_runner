<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetAvailabilityBlockoutReasonTypesTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('asset_availability_blockout_reason_types')->insert([
            [
                'name' => 'maintenance',
                'description' => 'Maintenance',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'repair',
                'description' => 'Repair',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'reserved',
                'description' => 'Reserved',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'other',
                'description' => 'Other',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
