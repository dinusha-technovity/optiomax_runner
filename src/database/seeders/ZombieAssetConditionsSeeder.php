<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZombieAssetConditionsSeeder extends Seeder
{
    /**
     * Seed the zombie_asset_conditions master table.
     *
     * Records with tenant_id = NULL are global / platform-wide defaults
     * available to all tenants.
     *
     * Run with:
     *   php artisan db:seed --class=ZombieAssetConditionsSeeder
     */
    public function run(): void
    {
        $now = now();

        $records = [
            [
                'name'          => 'excellent',
                'label'         => 'Excellent',
                'description'   => 'Asset is in perfect or near-perfect condition with no visible damage.',
                'display_order' => 1,
                'tenant_id'     => null,
                'deleted_at'    => null,
                'isactive'      => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'good',
                'label'         => 'Good',
                'description'   => 'Asset shows minor signs of wear but is fully functional.',
                'display_order' => 2,
                'tenant_id'     => null,
                'deleted_at'    => null,
                'isactive'      => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'fair',
                'label'         => 'Fair',
                'description'   => 'Asset shows moderate wear or minor damage; functional but may need maintenance.',
                'display_order' => 3,
                'tenant_id'     => null,
                'deleted_at'    => null,
                'isactive'      => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'poor',
                'label'         => 'Poor',
                'description'   => 'Asset is significantly damaged or degraded; functionality may be impaired.',
                'display_order' => 4,
                'tenant_id'     => null,
                'deleted_at'    => null,
                'isactive'      => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'unusable',
                'label'         => 'Unusable',
                'description'   => 'Asset is beyond repair or too damaged for any practical use.',
                'display_order' => 5,
                'tenant_id'     => null,
                'deleted_at'    => null,
                'isactive'      => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ];

        DB::table('zombie_asset_conditions')->insertOrIgnore($records);
    }
}
