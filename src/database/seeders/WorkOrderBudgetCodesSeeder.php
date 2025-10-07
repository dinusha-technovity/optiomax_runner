<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkOrderBudgetCodesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $budgetCodes = [
            [
                'code' => '1001',
                'name' => 'General Maintenance',
                'description' => 'Budget for routine maintenance activities',
                'isactive' => true,
                'tenant_id' => null, // or set to a specific tenant ID if needed
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '1002',
                'name' => 'Capital Projects',
                'description' => 'Budget for major capital improvement projects',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '1003',
                'name' => 'Emergency Repairs',
                'description' => 'Budget for unexpected emergency repairs',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '1004',
                'name' => 'Operational Supplies',
                'description' => 'Budget for day-to-day operational supplies',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '1005',
                'name' => 'Preventive Services',
                'description' => 'Budget for scheduled preventive maintenance services',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('work_order_budget_codes')->insert($budgetCodes);
    }
}
