<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowConditionSystemVariableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('workflow_condition_system_variable')->insert([
            [
                'name' => 'order total',
                'value' => 'order total',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
