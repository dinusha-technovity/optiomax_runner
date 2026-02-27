<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetUpgradeOutcomesSeeder extends Seeder
{
    public function run(): void
    {
        $outcomes = [
            [
                'id' => 1,
                'tenant_id' => null,
                'outcome_text' => 'Improved Performance',
                'description' => 'Expected improvement in processing speed and overall performance',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'tenant_id' => null,
                'outcome_text' => 'Extended Lifespan',
                'description' => 'Prolonged operational lifespan of the asset through upgrade or replacement',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'tenant_id' => null,
                'outcome_text' => 'Cost Reduction',
                'description' => 'Reduction in maintenance or operational costs over time',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'tenant_id' => null,
                'outcome_text' => 'Enhanced Safety',
                'description' => 'Improved safety standards and reduced risk of hazards',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'tenant_id' => null,
                'outcome_text' => 'Increased Efficiency',
                'description' => 'Higher operational efficiency and productivity output',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 6,
                'tenant_id' => null,
                'outcome_text' => 'Better Compliance',
                'description' => 'Improved alignment with regulatory and compliance requirements',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('asset_upgrade_replace_outcomes')->upsert(
            $outcomes,
            ['id'],
            ['outcome_text', 'description', 'is_active', 'updated_at']
        );
    }
}
