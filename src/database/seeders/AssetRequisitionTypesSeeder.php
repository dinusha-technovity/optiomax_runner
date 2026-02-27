<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssetRequisitionTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantId = null; // Adjust as needed for your environment
        $now = now();
        $types = [
             [ 'id'=>1,
                'tenant_id' => $tenantId,
                'code' => 'NEW',
                'name' => 'New',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id'=>2,
                'tenant_id' => $tenantId,
                'code' => 'UPGRADE',
                'name' => 'Upgrade',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [ 
                'id'=>3,
                'tenant_id' => $tenantId,
                'code' => 'REPLACE',
                'name' => 'Replace',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
           
        ];

        DB::table('asset_requisition_types')->upsert(
            $types,
            ['id'],
            ['name', 'is_active', 'updated_at']
        );
    }
}
