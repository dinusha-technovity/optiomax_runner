<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TenantPackagesSeeder extends Seeder
{
    /** 
     * Run the database seeds.
     */
    public function run(): void
    {
        $currentTime = Carbon::now();

        DB::table('tenant_packages')->insert([
            // Free Plan - Individual Only (No yearly version)
            [
                'name' => 'Free',
                'type' => 'month',
                'price' => 0.00,
                'discount_price' => null,
                'description' => 'All the basic features to explore the platform',
                'credits' => 0,
                'workflows' => 2,
                'users' => 1,
                'max_storage_gb' => 1,
                'support' => false,
                'max_retry_attempts' => 0, // No retries for free plan
                'retry_interval_days' => 0,
                'grace_period_days' => 0,
                'allowed_package_types' => json_encode(['INDIVIDUAL']),
                'features' => json_encode([
                    'basic_dashboard',
                    'limited_workflows',
                    'community_support'
                ]),
                'is_recurring' => false,
                'trial_days' => 0,
                'setup_fee' => 0.00,
                'stripe_price_id_monthly' => null,
                'stripe_price_id_yearly' => null,
                'stripe_product_id' => 'prod_free_plan',
                'isactive' => true,
                'is_popular' => false,
                'sort_order' => 1,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            
            // Starter Plans - Both Types
            [
                'name' => 'Starter',
                'type' => 'month',
                'price' => 10.00,
                'discount_price' => null,
                'description' => 'Perfect for small teams getting started',
                'credits' => 100,
                'workflows' => 15,
                'users' => 5,
                'max_storage_gb' => 10,
                'support' => true,
                'max_retry_attempts' => 3,
                'retry_interval_days' => 1,
                'grace_period_days' => 7,
                'allowed_package_types' => json_encode(['INDIVIDUAL', 'ENTERPRISE']),
                'features' => json_encode([
                    'dashboard',
                    'basic_workflows',
                    'email_support',
                    'api_access'
                ]),
                'is_recurring' => true,
                'trial_days' => 14,
                'setup_fee' => 0.00,
                'stripe_price_id_monthly' => 'price_starter_monthly',
                'stripe_price_id_yearly' => null,
                'stripe_product_id' => 'prod_starter_plan',
                'isactive' => true,
                'is_popular' => true,
                'sort_order' => 2,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'name' => 'Starter',
                'type' => 'year',
                'price' => 108.00,
                'discount_price' => 120.00, // Show original price for comparison
                'description' => 'Perfect for small teams getting started - Save 10% yearly',
                'credits' => 1200,
                'workflows' => 15,
                'users' => 5,
                'max_storage_gb' => 10,
                'support' => true,
                'max_retry_attempts' => 5,
                'retry_interval_days' => 1,
                'grace_period_days' => 14,
                'allowed_package_types' => json_encode(['INDIVIDUAL', 'ENTERPRISE']),
                'features' => json_encode([
                    'dashboard',
                    'basic_workflows',
                    'email_support',
                    'api_access',
                    'yearly_discount'
                ]),
                'is_recurring' => true,
                'trial_days' => 14,
                'setup_fee' => 0.00,
                'stripe_price_id_monthly' => null,
                'stripe_price_id_yearly' => 'price_starter_yearly',
                'stripe_product_id' => 'prod_starter_plan',
                'isactive' => true,
                'is_popular' => false,
                'sort_order' => 3,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],

            // Scale Plans - Both Types
            [
                'name' => 'Scale',
                'type' => 'month',
                'price' => 20.00,
                'discount_price' => null,
                'description' => 'Scale your business with advanced features',
                'credits' => 200,
                'workflows' => 25,
                'users' => 10,
                'max_storage_gb' => 25,
                'support' => true,
                'max_retry_attempts' => 5,
                'retry_interval_days' => 1,
                'grace_period_days' => 10,
                'allowed_package_types' => json_encode(['INDIVIDUAL', 'ENTERPRISE']),
                'features' => json_encode([
                    'advanced_dashboard',
                    'advanced_workflows',
                    'priority_support',
                    'advanced_api_access',
                    'integrations'
                ]),
                'is_recurring' => true,
                'trial_days' => 14,
                'setup_fee' => 0.00,
                'stripe_price_id_monthly' => 'price_scale_monthly',
                'stripe_price_id_yearly' => null,
                'stripe_product_id' => 'prod_scale_plan',
                'isactive' => true,
                'is_popular' => false,
                'sort_order' => 4,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'name' => 'Scale',
                'type' => 'year',
                'price' => 216.00,
                'discount_price' => 240.00,
                'description' => 'Scale your business with advanced features - Save 10% yearly',
                'credits' => 2400,
                'workflows' => 25,
                'users' => 10,
                'max_storage_gb' => 25,
                'support' => true,
                'max_retry_attempts' => 6,
                'retry_interval_days' => 1,
                'grace_period_days' => 14,
                'allowed_package_types' => json_encode(['INDIVIDUAL', 'ENTERPRISE']),
                'features' => json_encode([
                    'advanced_dashboard',
                    'advanced_workflows',
                    'priority_support',
                    'advanced_api_access',
                    'integrations',
                    'yearly_discount'
                ]),
                'is_recurring' => true,
                'trial_days' => 14,
                'setup_fee' => 0.00,
                'stripe_price_id_monthly' => null,
                'stripe_price_id_yearly' => 'price_scale_yearly',
                'stripe_product_id' => 'prod_scale_plan',
                'isactive' => true,
                'is_popular' => false,
                'sort_order' => 5,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],

            // Pro Plans - Both Types
            [
                'name' => 'Pro',
                'type' => 'month',
                'price' => 30.00,
                'discount_price' => null,
                'description' => 'Professional features for growing teams',
                'credits' => 300,
                'workflows' => 30,
                'users' => 15,
                'max_storage_gb' => 50,
                'support' => true,
                'max_retry_attempts' => 6,
                'retry_interval_days' => 1,
                'grace_period_days' => 14,
                'allowed_package_types' => json_encode(['INDIVIDUAL', 'ENTERPRISE']),
                'features' => json_encode([
                    'pro_dashboard',
                    'unlimited_workflows',
                    'dedicated_support',
                    'premium_integrations',
                    'analytics'
                ]),
                'is_recurring' => true,
                'trial_days' => 14,
                'setup_fee' => 0.00,
                'stripe_price_id_monthly' => 'price_pro_monthly',
                'stripe_price_id_yearly' => null,
                'stripe_product_id' => 'prod_pro_plan',
                'isactive' => true,
                'is_popular' => false,
                'sort_order' => 6,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'name' => 'Pro',
                'type' => 'year',
                'price' => 324.00,
                'discount_price' => 360.00,
                'description' => 'Professional features for growing teams - Save 10% yearly',
                'credits' => 3600,
                'workflows' => 30,
                'users' => 15,
                'max_storage_gb' => 50,
                'support' => true,
                'max_retry_attempts' => 7,
                'retry_interval_days' => 1,
                'grace_period_days' => 21,
                'allowed_package_types' => json_encode(['INDIVIDUAL', 'ENTERPRISE']),
                'features' => json_encode([
                    'pro_dashboard',
                    'unlimited_workflows',
                    'dedicated_support',
                    'premium_integrations',
                    'analytics',
                    'yearly_discount'
                ]),
                'is_recurring' => true,
                'trial_days' => 14,
                'setup_fee' => 0.00,
                'stripe_price_id_monthly' => null,
                'stripe_price_id_yearly' => 'price_pro_yearly',
                'stripe_product_id' => 'prod_pro_plan',
                'isactive' => true,
                'is_popular' => false,
                'sort_order' => 7,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],

            // Enterprise Plans - Enterprise Only
            [
                'name' => 'Enterprise',
                'type' => 'month',
                'price' => 100.00,
                'discount_price' => null,
                'description' => 'Enterprise-grade features and dedicated support',
                'credits' => 1000,
                'workflows' => 100,
                'users' => 50,
                'max_storage_gb' => 500,
                'support' => true,
                'max_retry_attempts' => 7,
                'retry_interval_days' => 1,
                'grace_period_days' => 21,
                'allowed_package_types' => json_encode(['ENTERPRISE']),
                'features' => json_encode([
                    'enterprise_dashboard',
                    'custom_workflows',
                    'dedicated_manager',
                    'sla_support',
                    'custom_integrations',
                    'advanced_analytics',
                    'compliance_features',
                    'white_labeling'
                ]),
                'is_recurring' => true,
                'trial_days' => 30,
                'setup_fee' => 500.00,
                'stripe_price_id_monthly' => 'price_enterprise_monthly',
                'stripe_price_id_yearly' => null,
                'stripe_product_id' => 'prod_enterprise_plan',
                'isactive' => true,
                'is_popular' => false,
                'sort_order' => 8,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'name' => 'Enterprise',
                'type' => 'year',
                'price' => 1080.00,
                'discount_price' => 1200.00,
                'description' => 'Enterprise-grade features and dedicated support - Save 10% yearly',
                'credits' => 12000,
                'workflows' => 100,
                'users' => 50,
                'max_storage_gb' => 500,
                'support' => true,
                'max_retry_attempts' => 7,
                'retry_interval_days' => 1,
                'grace_period_days' => 21,
                'allowed_package_types' => json_encode(['ENTERPRISE']),
                'features' => json_encode([
                    'enterprise_dashboard',
                    'custom_workflows',
                    'dedicated_manager',
                    'sla_support',
                    'custom_integrations',
                    'advanced_analytics',
                    'compliance_features',
                    'white_labeling',
                    'yearly_discount'
                ]),
                'is_recurring' => true,
                'trial_days' => 30,
                'setup_fee' => 500.00,
                'stripe_price_id_monthly' => null,
                'stripe_price_id_yearly' => 'price_enterprise_yearly',
                'stripe_product_id' => 'prod_enterprise_plan',
                'isactive' => true,
                'is_popular' => false,
                'sort_order' => 9,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
        ]);
    }
}
