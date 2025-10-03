<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PackageAddonsSeeder extends Seeder
{
    public function run(): void
    {
        $currentTime = Carbon::now();

        DB::table('package_addons')->insert([
            [
                'name' => 'Extra Credits',
                'slug' => 'extra-credits',
                'description' => 'Add 50 additional credits to your plan',
                'type' => 'credits',
                'price_monthly' => 5.00,
                'price_yearly' => 50.00,
                'quantity' => 50,
                'applicable_packages' => null, // Available for all packages
                'is_stackable' => true,
                'max_quantity' => 10,
                'stripe_price_id_monthly' => 'price_addon_credits_monthly',
                'stripe_price_id_yearly' => 'price_addon_credits_yearly',
                'isactive' => true,
                'sort_order' => 1,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'name' => 'Additional Users',
                'slug' => 'additional-users',
                'description' => 'Add 5 more users to your team',
                'type' => 'users',
                'price_monthly' => 8.00,
                'price_yearly' => 80.00,
                'quantity' => 5,
                'applicable_packages' => json_encode([2, 3, 4, 5, 6, 7]), // Not available for Free and Enterprise
                'is_stackable' => true,
                'max_quantity' => 5,
                'stripe_price_id_monthly' => 'price_addon_users_monthly',
                'stripe_price_id_yearly' => 'price_addon_users_yearly',
                'isactive' => true,
                'sort_order' => 2,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'name' => 'Extra Storage',
                'slug' => 'extra-storage',
                'description' => 'Add 10GB of additional storage',
                'type' => 'storage',
                'price_monthly' => 3.00,
                'price_yearly' => 30.00,
                'quantity' => 10,
                'applicable_packages' => null,
                'is_stackable' => true,
                'max_quantity' => 20,
                'stripe_price_id_monthly' => 'price_addon_storage_monthly',
                'stripe_price_id_yearly' => 'price_addon_storage_yearly',
                'isactive' => true,
                'sort_order' => 3,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'name' => 'Advanced Workflows',
                'slug' => 'advanced-workflows',
                'description' => 'Add 10 additional workflow slots',
                'type' => 'workflows',
                'price_monthly' => 6.00,
                'price_yearly' => 60.00,
                'quantity' => 10,
                'applicable_packages' => json_encode([2, 3, 4, 5, 6, 7]),
                'is_stackable' => true,
                'max_quantity' => 8,
                'stripe_price_id_monthly' => 'price_addon_workflows_monthly',
                'stripe_price_id_yearly' => 'price_addon_workflows_yearly',
                'isactive' => true,
                'sort_order' => 4,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'name' => 'Priority Support',
                'slug' => 'priority-support',
                'description' => 'Get priority support with faster response times',
                'type' => 'feature',
                'price_monthly' => 15.00,
                'price_yearly' => 150.00,
                'quantity' => 1,
                'applicable_packages' => json_encode([2, 3, 4, 5]), // Only for Starter and Scale plans
                'is_stackable' => false,
                'max_quantity' => 1,
                'stripe_price_id_monthly' => 'price_addon_priority_support_monthly',
                'stripe_price_id_yearly' => 'price_addon_priority_support_yearly',
                'isactive' => true,
                'sort_order' => 5,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ]
        ]);
    }
}
