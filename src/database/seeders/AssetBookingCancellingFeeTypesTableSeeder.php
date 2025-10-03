<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetBookingCancellingFeeTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('asset_booking_cancelling_fee_types')->insert([
            [
                'name' => 'Fixed Amount',
                'description' => 'A fixed booking cancelling fee amount.',
                'deleted_at' => null,
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Percentage',
                'description' => 'Percentage-based booking cancelling fee.',
                'deleted_at' => null,
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}