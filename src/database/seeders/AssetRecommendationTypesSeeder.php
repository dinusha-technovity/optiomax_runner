<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetRecommendationTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
           
            [   'id'=>1,
                'tenant_id' => null,
                'code' => 'UPGRADE_ASSET_REQUISITION',
                'name' => 'Upgrade Asset Requisition',
                'description' => 'Recommendation for upgrading an existing asset',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'=>2,
                'tenant_id' => null,
                'code' => 'REPLACE_ASSET_REQUISITION',
                'name' => 'Replace Asset Requisition',
                'description' => 'Recommendation for replacing an existing asset',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('asset_recommendation_types')->upsert(
            $types,
            ['id'],
            ['name', 'description', 'is_active', 'updated_at']
        );
    }
}
