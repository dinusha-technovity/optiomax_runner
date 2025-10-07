<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantWorkflowConditionTagDefinitionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    { 
        // Insert initial tag data into `tag_definitions`
        DB::table('workflow_condition_tag_definitions')->insert([
            [
                'tag_name' => 'value',
                'query_template' => 'SELECT current_value FROM settings WHERE setting_name = "default_value"',
                'workflow_request_types' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tag_name' => 'assetreq1',
                'query_template' => 'SELECT asset_code FROM assets WHERE asset_id = 123',
                'workflow_request_types' => 3,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tag_name' => 'supplier type',
                'query_template' => 'CALL get_supplier_type(?, OUT age_result)',
                'workflow_request_types' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
