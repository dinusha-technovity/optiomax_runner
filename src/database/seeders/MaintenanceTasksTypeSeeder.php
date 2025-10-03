<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaintenanceTasksTypeSeeder extends Seeder
{
    protected $tenantId;

    public function __construct()
    {
        // Retrieve the selected user name from the service container
        $this->tenantId = app()->make('selectedTenantId');
    }
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('maintenance_tasks_type')->insert([
            [
                'name' => 'Routine Inspection',
                'tenant_id' => $this->tenantId,
                'isactive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Preventive Maintenance',
                'tenant_id' => $this->tenantId,
                'isactive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Corrective Maintenance',
                'tenant_id' => $this->tenantId,
                'isactive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Emergency Repair',
                'tenant_id' => $this->tenantId,
                'isactive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
