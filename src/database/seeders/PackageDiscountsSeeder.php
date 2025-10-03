<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PackageDiscountsSeeder extends Seeder
{
    public function run(): void
    {
        $currentTime = Carbon::now();

        DB::table('package_discounts')->insert([
            [
                'name' => 'New Customer 20% Off',
                'code' => 'WELCOME20',
                'description' => '20% discount for new customers on their first subscription',
                'type' => 'percentage',
                'value' => 20.00,
                'applicable_packages' => null, // All packages
                'applicable_package_types' => json_encode(['INDIVIDUAL', 'ENTERPRISE']),
                'billing_cycles' => 'both',
                'is_first_time_only' => true,
                'usage_limit' => 1000,
                'usage_count' => 0,
                'usage_limit_per_customer' => 1,
                'minimum_amount' => 10.00,
                'valid_from' => $currentTime,
                'valid_until' => $currentTime->copy()->addMonths(6),
                'stripe_coupon_id' => 'coupon_welcome20',
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'name' => 'Yearly Plan Bonus',
                'code' => 'YEARLY15',
                'description' => 'Additional 15% off on yearly plans',
                'type' => 'percentage',
                'value' => 15.00,
                'applicable_packages' => null,
                'applicable_package_types' => json_encode(['INDIVIDUAL', 'ENTERPRISE']),
                'billing_cycles' => 'yearly',
                'is_first_time_only' => false,
                'usage_limit' => null,
                'usage_count' => 0,
                'usage_limit_per_customer' => 1,
                'minimum_amount' => 50.00,
                'valid_from' => $currentTime,
                'valid_until' => $currentTime->copy()->addYear(),
                'stripe_coupon_id' => 'coupon_yearly15',
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'name' => 'Enterprise Starter',
                'code' => 'ENTERPRISE50',
                'description' => '$50 off for enterprise customers',
                'type' => 'fixed_amount',
                'value' => 50.00,
                'applicable_packages' => json_encode([8, 9]), // Only Enterprise plans
                'applicable_package_types' => json_encode(['ENTERPRISE']),
                'billing_cycles' => 'both',
                'is_first_time_only' => true,
                'usage_limit' => 100,
                'usage_count' => 0,
                'usage_limit_per_customer' => 1,
                'minimum_amount' => 100.00,
                'valid_from' => $currentTime,
                'valid_until' => $currentTime->copy()->addMonths(3),
                'stripe_coupon_id' => 'coupon_enterprise50',
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'name' => 'Black Friday Special',
                'code' => 'BLACKFRIDAY30',
                'description' => '30% off everything - Limited time offer',
                'type' => 'percentage',
                'value' => 30.00,
                'applicable_packages' => null,
                'applicable_package_types' => json_encode(['INDIVIDUAL', 'ENTERPRISE']),
                'billing_cycles' => 'both',
                'is_first_time_only' => false,
                'usage_limit' => 500,
                'usage_count' => 0,
                'usage_limit_per_customer' => 1,
                'minimum_amount' => 20.00,
                'valid_from' => $currentTime->copy()->addMonths(10), // November
                'valid_until' => $currentTime->copy()->addMonths(10)->addDays(7), // 7 days only
                'stripe_coupon_id' => 'coupon_blackfriday30',
                'isactive' => false, // Will be activated during Black Friday
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'name' => 'Student Discount',
                'code' => 'STUDENT50',
                'description' => '50% off for students and educational institutions',
                'type' => 'percentage',
                'value' => 50.00,
                'applicable_packages' => json_encode([2, 3, 4, 5]), // Starter, Scale, Pro plans only
                'applicable_package_types' => json_encode(['INDIVIDUAL']),
                'billing_cycles' => 'both',
                'is_first_time_only' => false,
                'usage_limit' => null,
                'usage_count' => 0,
                'usage_limit_per_customer' => 1,
                'minimum_amount' => 10.00,
                'valid_from' => $currentTime,
                'valid_until' => null, // No expiry
                'stripe_coupon_id' => 'coupon_student50',
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ]
        ]);
    }
}
