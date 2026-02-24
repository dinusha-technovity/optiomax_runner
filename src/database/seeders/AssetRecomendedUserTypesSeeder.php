<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetRecomendedUserTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userTypes = [
            [
                'tenant_id' => null,
                'code' => 'MAINT_LEAD',
                'name' => 'Maintenance Team Leader',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => null,
                'code' => 'AUDITOR',
                'name' => 'Auditor',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        DB::table('asset_recommend_user_types')->insert($userTypes);
    }
}