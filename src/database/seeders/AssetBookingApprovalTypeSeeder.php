<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetBookingApprovalTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('asset_booking_approval_types')->truncate();
        DB::table('asset_booking_approval_types')->insert([
            [
                'name' => 'Auto Approval',
                'description' => 'Booking is approved automatically',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Workflow Approval',
                'description' => 'Booking requires approval through a workflow',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
