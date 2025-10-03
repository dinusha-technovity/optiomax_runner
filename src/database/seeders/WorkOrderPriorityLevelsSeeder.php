<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkOrderPriorityLevelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('work_order_priority_levels')->truncate();

        $priorityLevels = [
            [
                'name'  => 'Low',
                'level' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'  => 'Medium',
                'level' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'  => 'High',
                'level' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'  => 'Critical',
                'level' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Seed multiple priority levels
        foreach ($priorityLevels as $priority) {
            DB::table('work_order_priority_levels')->insert($priority);
        }
    }
}
