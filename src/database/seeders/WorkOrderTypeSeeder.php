<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkOrderTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $workOrderTypes = [
            [
                'name' => 'Preventive Maintenance',
                'description' => 'Scheduled maintenance to prevent equipment failures and maintain optimal performance',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Corrective Maintenance',
                'description' => 'Repairs performed to correct equipment faults and restore functionality',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Inspection',
                'description' => 'Routine checks to assess equipment condition and identify potential issues',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Calibration',
                'description' => 'Adjustment of equipment to ensure accurate and precise operation',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Installation',
                'description' => 'Setting up and commissioning new equipment or systems',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Upgrade',
                'description' => 'Enhancements or modifications to existing equipment or systems',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Safety Check',
                'description' => 'Verification of safety systems and compliance with regulations',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Testing',
                'description' => 'Performance evaluation and validation of equipment or systems',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Emergency Repair',
                'description' => 'Urgent repairs to address critical failures or safety hazards',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Decommissioning',
                'description' => 'Safe removal and disposal of equipment or systems',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('work_order_types')->insert($workOrderTypes);
    }
}
