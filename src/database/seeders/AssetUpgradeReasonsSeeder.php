<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AssetUpgradeReasonsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantId = null; // Change as needed for your environment
        $now = Carbon::now();

        $reasons = [
            [
                'code' => 'capacity_insufficiency',
                'title' => 'Capacity or Capability Insufficiency',
                'description' => 'The asset no longer meets current workload, usage, or operational demands.',
                'is_active' => true,
                'tenant_id' => $tenantId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'performance_improvement',
                'title' => 'Performance Improvement Required',
                'description' => 'Upgrade needed to improve speed, efficiency, accuracy, or output quality.',
                'is_active' => true,
                'tenant_id' => $tenantId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'operational_bottlenecks',
                'title' => 'Operational Bottlenecks Identified',
                'description' => 'Existing asset configuration causes delays or limits process flow.',
                'is_active' => true,
                'tenant_id' => $tenantId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'new_requirements',
                'title' => 'New Usage or Functional Requirements',
                'description' => 'Asset must support new tasks, processes, or operating conditions.',
                'is_active' => true,
                'tenant_id' => $tenantId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'cost_optimization',
                'title' => 'Cost Optimization Through Enhancement',
                'description' => 'Upgrading is more cost-effective than frequent repairs or full replacement.',
                'is_active' => true,
                'tenant_id' => $tenantId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'other',
                'title' => 'Other',
                'description' => 'Specify other reason for asset upgrade.',
                'is_active' => true,
                'tenant_id' => $tenantId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('asset_upgrade_reasons')->insert($reasons);
    }
}
