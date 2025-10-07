<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

class AssetMaintainScheduleParametersSeeder extends Seeder
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

        // Fetch existing schedule types
        $scheduleTypes = DB::table('asset_maintain_schedule_types')->pluck('id', 'name');

        // Insert schedule parameters
        DB::table('asset_maintain_schedule_parameters')->insert([
            [
                'name' => 'Oil Change Interval',
                'asset_maintain_schedule_type' => $scheduleTypes['Manufacturer Recommendation'] ?? null,
                'tenant_id' => $this->tenantId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Engine Run Hours',
                'asset_maintain_schedule_type' => $scheduleTypes['Usage-Based Maintenance'] ?? null,
                'tenant_id' => $this->tenantId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Risk Assessment Frequency',
                'asset_maintain_schedule_type' => $scheduleTypes['Criticality Based Maintenance'] ?? null,
                'tenant_id' => $this->tenantId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        ]);
    }
}
