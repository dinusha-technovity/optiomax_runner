<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZombieAssetReporterTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name'        => 'asset_auditor',
                'label'       => 'Asset Auditor',
                'description' => 'Assigned auditor conducting a formal asset audit session',
                'isactive'    => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'name'        => 'maintenance_supervisor',
                'label'       => 'Maintenance Supervisor',
                'description' => 'Maintenance supervisor who encounters an unregistered asset during maintenance activities',
                'isactive'    => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ];

        foreach ($types as $type) {
            DB::table('zombie_asset_reporter_types')
                ->updateOrInsert(['name' => $type['name']], $type);
        }
    }
}
