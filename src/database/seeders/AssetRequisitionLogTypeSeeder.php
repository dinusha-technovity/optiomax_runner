<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetRequisitionLogTypeSeeder extends Seeder
{
    public function run(): void
    {
        $logTypes = [
            [
                'id' => 1,
                'code' => 'ASSET_REQUISITION_CREATED',
                'name' => 'Asset Requisition Created',
                'description' => 'Asset requisition created',
            ],
            [
                'id' => 2,
                'code' => 'ASSET_REQUISITION_REJECTED_BY_MAINTENANCE_LEADER',
                'name' => 'Asset Requisition Rejected by Maintenance Leader',
                'description' => 'Asset requisition rejected by maintenance leader',
            ],
            [
                'id' => 3,
                'code' => 'ASSET_REQUISITION_RECOMMENDED_FOR_UPGRADE',
                'name' => 'Asset Requisition Recommended for Upgrade',
                'description' => 'Asset requisition recommended for upgrade',
            ],
            [
                'id' => 4,
                'code' => 'ASSET_REQUISITION_RECOMMENDED_FOR_REPLACE',
                'name' => 'Asset Requisition Recommended for Replace',
                'description' => 'Asset requisition recommended for replace',
            ],
            [
                'id' => 5,
                'code' => 'ASSET_OWNER_ACCEPTED_REPLACEMENT',
                'name' => 'Asset Owner Accepted Replacement',
                'description' => 'Asset owner accepted replacement',
            ],
            [
                'id' => 6,
                'code' => 'ASSET_OWNER_ACCEPTED_UPGRADE',
                'name' => 'Asset Owner Accepted Upgrade',
                'description' => 'Asset owner accepted upgrade',
            ],
            [
                'id' => 7,
                'code' => 'ASSET_REQUISITION_SET_ON_HOLD',
                'name' => 'Asset Requisition Set On Hold',
                'description' => 'Asset requisition set to on hold',
            ],
            [
                'id' => 8,
                'code' => 'ASSET_UPGRADE_SUBMITTED_TO_WORKORDER',
                'name' => 'Asset Upgrade Submitted to Workorder',
                'description' => 'Asset upgrade submitted to workorder',
            ],
            [
                'id' => 9,
                'code' => 'ASSET_REQUISITION_UPGRADE_COMPLETED',
                'name' => 'Asset Requisition Upgrade Completed',
                'description' => 'Asset requisition upgrade completed',
            ],
            [
                'id' => 10,
                'code' => 'ASSET_REQUISITION_REPLACE_COMPLETED',
                'name' => 'Asset Requisition Replace Completed',
                'description' => 'Asset requisition replace completed',
            ],
        ];

        DB::table('asset_requisition_log_types')->upsert(
            $logTypes,
            ['id'],
            ['code', 'name', 'description']
        );
    }
}