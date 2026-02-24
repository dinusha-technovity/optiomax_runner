<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class WorkOrderInitiateTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantId = null;
        $now = Carbon::now();

        $types = [
            [
                'id' => 1,
                'tenant_id'   => $tenantId,
                'code'        => 'direct',
                'name'        => 'Direct',
                'description' => 'Work order is initiated directly by an authorized user or department without any prior request or trigger process.',
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [   
                'id' => 2,
                'tenant_id'   => $tenantId,
                'code'        => 'maintenance',
                'name'        => 'Maintenance',
                'description' => 'Work order is initiated as part of a scheduled or reactive maintenance plan to keep assets in optimal operating condition.',
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'id' => 3,
                'tenant_id'   => $tenantId,
                'code'        => 'asset_requisition_upgrade',
                'name'        => 'Asset Requisition Upgrade',
                'description' => 'Work order is initiated to fulfill an approved asset requisition upgrade request, covering enhancement or replacement of existing assets.',
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'id' => 4,
                'tenant_id'   => $tenantId,
                'code'        => 'incident',
                'name'        => 'Incident',
                'description' => 'Work order is initiated in response to an unexpected incident, failure, or breakdown requiring immediate corrective action.',
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ];

        DB::table('workorder_initiate_types')->upsert(
            $types,
            ['id'],
            ['name', 'description', 'is_active', 'updated_at']
        );
    }
}
