<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkOrderMaintenanceTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $maintenanceTypes = [
            [
                'name' => 'Routine',
                'description' => 'Scheduled regular maintenance activities',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Emergency',
                'description' => 'Urgent repairs for unexpected breakdowns',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Ad-hoc',
                'description' => 'Non-scheduled maintenance as needed',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Preventive',
                'description' => 'Planned maintenance to prevent failures',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Corrective',
                'description' => 'Repairs to restore equipment to working condition',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('work_order_maintenance_types')->insert($maintenanceTypes);
    }
}
