<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetBookingCancellationRefundPolicyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('asset_booking_cancellation_refund_policy_type')->insert([
            [
                'name' => 'Full Refund (minus fees)',
                'description' => 'Customer receives a full refund minus any applicable fees.',
                'deleted_at' => null,
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Partial Refund',
                'description' => 'Customer receives a partial refund as per policy.',
                'deleted_at' => null,
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'No Refund',
                'description' => 'No refund will be issued for cancellations.',
                'deleted_at' => null,
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Store Credit Only',
                'description' => 'Customer receives store credit instead of a refund.',
                'deleted_at' => null,
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
