<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssetBookingTypeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('asset_booking_type')->insert([
            [
                'name' => 'Internal',
                'description' => 'Asset Booking for internal users.',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'External',
                'description' => 'Asset Booking for external users.',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        ]);
    }
}
