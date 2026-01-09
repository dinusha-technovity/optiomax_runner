<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetAuditVariableTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $assetAuditVariableTypes = [
            [
                'name' => 'Physical Condition',
                'description' => 'Assessment of physical state, wear, and structural integrity',
                'is_active' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'System / Operational Condition',
                'description' => 'Assessment of operational performance and functionality',
                'is_active' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Compliance & Usage',
                'description' => 'Assessment of regulatory compliance and usage patterns',
                'is_active' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Risk & Replacement Need',
                'description' => 'Assessment of risk level and replacement requirements',
                'is_active' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('asset_audit_variable_type')->insert($assetAuditVariableTypes);
    }
}
