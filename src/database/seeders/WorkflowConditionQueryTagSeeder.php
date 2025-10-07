<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowConditionQueryTagSeeder extends Seeder
{
    public function run(): void
    {
        // Truncate the table before seeding
        DB::table('workflow_condition_query_tag')->truncate();

        // Insert new data
        DB::table('workflow_condition_query_tag')->insert([
            [
                'name' => 'get supplier type',
                'value' => 'get_supplier_type',
                'query' => 'SELECT supplier_type FROM suppliers WHERE id = $1::BIGINT',
                'type' => 'query',
                'params' => json_encode(['{{supplier_id}}']),
                'options' => json_encode([
                    ['value' => 'Individual', 'label' => 'Individual'],
                    ['value' => 'Company', 'label' => 'Company']
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
