<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AssetMaintenancePriorityLevelsSeeder extends Seeder
{
    public function run(): void
    {
        $priorityLevels = [
            [
                'value' => 'critical',
                'label' => 'Critical (stops operation/safety issue)',
                'color' => 'bg-red-100 text-red-800',
                'description' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'value' => 'high',
                'label' => 'High (major disruption)',
                'color' => 'bg-orange-100 text-orange-800',
                'description' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'value' => 'medium',
                'label' => 'Medium (limited impact)',
                'color' => 'bg-yellow-100 text-yellow-800',
                'description' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'value' => 'low',
                'label' => 'Low (minor or cosmetic issue)',
                'color' => 'bg-green-100 text-green-800',
                'description' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('asset_maintenance_incident_report_priority_levels')->insert($priorityLevels);
    }
}