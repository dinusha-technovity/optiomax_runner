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
            // Free Plan
            [
                'name' => 'Free',
                'slug' => 'free-plan',
                'billing_type' => 'monthly',
                'base_price_monthly' => 0.00,
                'base_price_yearly' => 0.00,
                'description' => 'All the basic features to explore the platform',
                'terms_conditions' => 'Free plan terms: Limited features, community support only, data retention 30 days.',
                'charge_immediately_on_signup' => false, // No charge for free plan
                'prorated_billing' => false,
                'allow_downgrades' => false,
                'allow_upgrades' => true,
                'base_limits' => json_encode([
                    'credits' => 0,
                    'workflows' => 2,
                    'users' => 1,
                    'storage_gb' => 1,
                    'api_calls_per_month' => 1000
                ]),
                'max_retry_attempts' => 0,
                'retry_interval_days' => 0,
                'grace_period_days' => 0,
                'allowed_regions' => null, // Available worldwide
                'allowed_package_types' => json_encode(['INDIVIDUAL']),
                'compliance_requirements' => json_encode(['GDPR', 'CCPA']),
                'tax_codes' => json_encode([
                    'US' => 'txcd_10000000', // Software as a Service
                    'EU' => 'EU_DIGITAL_SERVICE',
                    'default' => 'SOFTWARE_SERVICE'
                ]),
                'is_recurring' => false,
                'trial_days' => 0,
                'trial_requires_payment_method' => false,
                'setup_fee' => 0.00,
                'cancellation_policy' => 'immediate',
                'stripe_price_id_monthly' => null,
                'stripe_price_id_yearly' => null,
                'stripe_product_id' => 'prod_free_plan',
                'isactive' => true,
                'is_popular' => false,
                'is_legacy' => false,
                'sort_order' => 1,
                'legal_last_updated' => $currentTime,
                'legal_version' => '1.0',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            
            // Starter Plan
            [
                'name' => 'Starter',
                'slug' => 'starter-plan',
                'billing_type' => 'both',
                'base_price_monthly' => 29.00,
                'base_price_yearly' => 290.00, // 16.7% discount
                'description' => 'Perfect for small teams getting started with advanced features',
                'terms_conditions' => 'Standard subscription terms apply. 14-day trial included. Cancel anytime.',
                'charge_immediately_on_signup' => true, // Charge immediately after trial
                'prorated_billing' => true,
                'allow_downgrades' => true,
                'allow_upgrades' => true,
                'base_limits' => json_encode([
                    'credits' => 1000,
                    'workflows' => 15,
                    'users' => 5,
                    'storage_gb' => 25,
                    'api_calls_per_month' => 50000,
                    'data_retention_days' => 365
                ]),
                'max_retry_attempts' => 3,
                'retry_interval_days' => 3,
                'grace_period_days' => 7,
                'allowed_regions' => null,
                'allowed_package_types' => json_encode(['INDIVIDUAL', 'ENTERPRISE']),
                'compliance_requirements' => json_encode(['GDPR', 'CCPA', 'SOC2', 'ISO27001']),
                'tax_codes' => json_encode([
                    'US' => 'txcd_10000000',
                    'EU' => 'EU_DIGITAL_SERVICE',
                    'UK' => 'UK_DIGITAL_SERVICE',
                    'default' => 'SOFTWARE_SERVICE'
                ]),
                'is_recurring' => true,
                'trial_days' => 14,
                'trial_requires_payment_method' => true,
                'setup_fee' => 0.00,
                'cancellation_policy' => 'end_of_period',
                'stripe_price_id_monthly' => 'price_starter_monthly',
                'stripe_price_id_yearly' => 'price_starter_yearly',
                'stripe_product_id' => 'prod_starter_plan',
                'isactive' => true,
                'is_popular' => true,
                'is_legacy' => false,
                'sort_order' => 2,
                'legal_last_updated' => $currentTime,
                'legal_version' => '1.2',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],

            // Professional Plan
            [
                'name' => 'Professional',
                'slug' => 'professional-plan',
                'billing_type' => 'both',
                'base_price_monthly' => 79.00,
                'base_price_yearly' => 790.00,
                'description' => 'Advanced features for growing businesses and teams',
                'terms_conditions' => 'Professional terms include SLA guarantees, priority support, and enhanced security features.',
                'charge_immediately_on_signup' => true,
                'prorated_billing' => true,
                'allow_downgrades' => true,
                'allow_upgrades' => true,
                'base_limits' => json_encode([
                    'credits' => 5000,
                    'workflows' => 50,
                    'users' => 25,
                    'storage_gb' => 100,
                    'api_calls_per_month' => 250000,
                    'data_retention_days' => 1095 // 3 years
                ]),
                'max_retry_attempts' => 5,
                'retry_interval_days' => 2,
                'grace_period_days' => 14,
                'allowed_regions' => null,
                'allowed_package_types' => json_encode(['INDIVIDUAL', 'ENTERPRISE']),
                'compliance_requirements' => json_encode(['GDPR', 'CCPA', 'SOC2', 'ISO27001', 'HIPAA_READY']),
                'tax_codes' => json_encode([
                    'US' => 'txcd_10000000',
                    'EU' => 'EU_DIGITAL_SERVICE',
                    'UK' => 'UK_DIGITAL_SERVICE',
                    'CA' => 'CA_DIGITAL_SERVICE',
                    'default' => 'SOFTWARE_SERVICE'
                ]),
                'is_recurring' => true,
                'trial_days' => 14,
                'trial_requires_payment_method' => true,
                'setup_fee' => 0.00,
                'cancellation_policy' => 'end_of_period',
                'stripe_price_id_monthly' => null, // Will be set by stripe:setup-products command
                'stripe_price_id_yearly' => null,  // Will be set by stripe:setup-products command
                'stripe_product_id' => null,       // Will be set by stripe:setup-products command
                'isactive' => true,
                'is_popular' => false,
                'is_legacy' => false,
                'sort_order' => 3,
                'legal_last_updated' => $currentTime,
                'legal_version' => '1.3',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],

            // Enterprise Plan
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise-plan',
                'billing_type' => 'both',
                'base_price_monthly' => 299.00,
                'base_price_yearly' => 2990.00, // 16.7% discount
                'description' => 'Enterprise-grade features with dedicated support and custom solutions',
                'terms_conditions' => 'Enterprise agreement with custom terms, SLA guarantees, dedicated support, and compliance certifications.',
                'charge_immediately_on_signup' => false, // Enterprise might need approval
                'prorated_billing' => true,
                'allow_downgrades' => false, // Enterprise usually doesn't downgrade
                'allow_upgrades' => true,
                'base_limits' => json_encode([
                    'credits' => 'unlimited',
                    'workflows' => 'unlimited',
                    'users' => 'unlimited',
                    'storage_gb' => 1000,
                    'api_calls_per_month' => 'unlimited',
                    'data_retention_days' => 'unlimited'
                ]),
                'max_retry_attempts' => 7,
                'retry_interval_days' => 1,
                'grace_period_days' => 30,
                'allowed_regions' => null,
                'allowed_package_types' => json_encode(['ENTERPRISE']),
                'compliance_requirements' => json_encode([
                    'GDPR', 'CCPA', 'SOC2_TYPE2', 'ISO27001', 'HIPAA', 'SOX', 'PCI_DSS', 'FedRAMP_READY'
                ]),
                'tax_codes' => json_encode([
                    'US' => 'txcd_10000000',
                    'EU' => 'EU_B2B_DIGITAL_SERVICE',
                    'UK' => 'UK_B2B_DIGITAL_SERVICE',
                    'CA' => 'CA_B2B_DIGITAL_SERVICE',
                    'default' => 'ENTERPRISE_SOFTWARE_SERVICE'
                ]),
                'is_recurring' => true,
                'trial_days' => 30,
                'trial_requires_payment_method' => false, // Enterprise trials might not require payment method
                'setup_fee' => 1000.00, // Enterprise setup fee
                'cancellation_policy' => 'with_penalty',
                'stripe_price_id_monthly' => 'price_enterprise_monthly',
                'stripe_price_id_yearly' => 'price_enterprise_yearly',
                'stripe_product_id' => 'prod_enterprise_plan',
                'isactive' => true,
                'is_popular' => false,
                'is_legacy' => false,
                'sort_order' => 4,
                'legal_last_updated' => $currentTime,
                'legal_version' => '2.0',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
        ]);
    }
}
