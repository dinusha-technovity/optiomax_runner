<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZombieAssetValueRangesSeeder extends Seeder
{
    /**
     * Seed the zombie_asset_value_ranges master table.
     *
     * Records with tenant_id = NULL are global / platform-wide defaults
     * available to all tenants.
     *
     * Run with:
     *   php artisan db:seed --class=ZombieAssetValueRangesSeeder
     */
    public function run(): void
    {
        $now = now();

        $records = [
            [
                'name'          => '<1000',
                'label'         => 'Under 1,000',
                'description'   => 'Estimated value is less than 1,000.',
                'min_value'     => null,
                'max_value'     => 999.99,
                'display_order' => 1,
                'tenant_id'     => null,
                'deleted_at'    => null,
                'isactive'      => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => '1000-5000',
                'label'         => '1,000 – 5,000',
                'description'   => 'Estimated value is between 1,000 and 5,000.',
                'min_value'     => 1000.00,
                'max_value'     => 5000.00,
                'display_order' => 2,
                'tenant_id'     => null,
                'deleted_at'    => null,
                'isactive'      => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => '5000-10000',
                'label'         => '5,000 – 10,000',
                'description'   => 'Estimated value is between 5,000 and 10,000.',
                'min_value'     => 5000.01,
                'max_value'     => 10000.00,
                'display_order' => 3,
                'tenant_id'     => null,
                'deleted_at'    => null,
                'isactive'      => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => '10000-50000',
                'label'         => '10,000 – 50,000',
                'description'   => 'Estimated value is between 10,000 and 50,000.',
                'min_value'     => 10000.01,
                'max_value'     => 50000.00,
                'display_order' => 4,
                'tenant_id'     => null,
                'deleted_at'    => null,
                'isactive'      => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => '>50000',
                'label'         => 'Over 50,000',
                'description'   => 'Estimated value exceeds 50,000.',
                'min_value'     => 50000.01,
                'max_value'     => null,
                'display_order' => 5,
                'tenant_id'     => null,
                'deleted_at'    => null,
                'isactive'      => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ];

        DB::table('zombie_asset_value_ranges')->insertOrIgnore($records);
    }
}
