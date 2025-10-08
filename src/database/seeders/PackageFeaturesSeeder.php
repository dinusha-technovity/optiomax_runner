<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PackageFeaturesSeeder extends Seeder
{
    public function run(): void
    {
        $currentTime = Carbon::now();
        
        // Get package IDs by slug
        $packages = DB::table('tenant_packages')->pluck('id', 'slug');
        
        $features = [
            // Priority Support
            [
                'feature_key' => 'priority_support',
                'feature_name' => 'Priority Support',
                'description' => 'Access to priority customer support with faster response times',
                'feature_type' => 'boolean',
                'value_type' => 'boolean',
                'packages' => ['starter-plan', 'professional-plan', 'enterprise-plan'],
                'default_value' => 'true',
                'additional_cost_monthly' => 0,
                'is_billable' => false,
            ],
            // Dedicated Account Manager
            [
                'feature_key' => 'dedicated_account_manager',
                'feature_name' => 'Dedicated Account Manager',
                'description' => 'Personal account manager for enterprise customers',
                'feature_type' => 'service',
                'value_type' => 'boolean',
                'packages' => ['enterprise-plan'],
                'default_value' => 'true',
                'additional_cost_monthly' => 500,
                'is_billable' => true,
            ],
            // Advanced Analytics
            [
                'feature_key' => 'advanced_analytics',
                'feature_name' => 'Advanced Analytics',
                'description' => 'Access to advanced analytics and reporting features',
                'feature_type' => 'feature',
                'value_type' => 'boolean',
                'packages' => ['professional-plan', 'enterprise-plan'],
                'default_value' => 'true',
                'additional_cost_monthly' => 0,
                'is_billable' => false,
            ],
            // White Labeling
            [
                'feature_key' => 'white_labeling',
                'feature_name' => 'White Labeling',
                'description' => 'Custom branding and white-label solutions',
                'feature_type' => 'feature',
                'value_type' => 'boolean',
                'packages' => ['enterprise-plan'],
                'default_value' => 'true',
                'additional_cost_monthly' => 200,
                'is_billable' => true,
            ],
            // Custom Integrations
            [
                'feature_key' => 'custom_integrations',
                'feature_name' => 'Custom Integrations',
                'description' => 'Custom API integrations and webhooks',
                'feature_type' => 'service',
                'value_type' => 'integer',
                'packages' => ['professional-plan', 'enterprise-plan'],
                'default_value' => '5',
                'max_value' => '50',
                'additional_cost_monthly' => 50,
                'is_billable' => true,
                'is_metered' => true,
                'metered_rate' => 10.00,
            ],
            // API Rate Limits
            [
                'feature_key' => 'api_rate_limit',
                'feature_name' => 'API Rate Limit',
                'description' => 'Number of API calls per minute allowed',
                'feature_type' => 'limit',
                'value_type' => 'integer',
                'packages' => ['starter-plan', 'professional-plan', 'enterprise-plan'],
                'default_value' => '100',
                'max_value' => '10000',
                'additional_cost_monthly' => 0,
                'is_billable' => false,
            ],
            // Data Retention
            [
                'feature_key' => 'data_retention_months',
                'feature_name' => 'Data Retention Period',
                'description' => 'How long data is retained in months',
                'feature_type' => 'limit',
                'value_type' => 'integer',
                'packages' => ['starter-plan', 'professional-plan', 'enterprise-plan'],
                'default_value' => '12',
                'max_value' => 'unlimited',
                'additional_cost_monthly' => 0,
                'is_billable' => false,
            ],
        ];

        $insertData = [];
        foreach ($features as $feature) {
            foreach ($feature['packages'] as $packageSlug) {
                if (isset($packages[$packageSlug])) {
                    $insertData[] = [
                        'package_id' => $packages[$packageSlug],
                        'feature_key' => $feature['feature_key'],
                        'feature_name' => $feature['feature_name'],
                        'description' => $feature['description'],
                        'feature_type' => $feature['feature_type'],
                        'value_type' => $feature['value_type'],
                        'default_value' => $feature['default_value'],
                        'max_value' => $feature['max_value'] ?? null,
                        'min_value' => $feature['min_value'] ?? null,
                        'additional_cost_monthly' => $feature['additional_cost_monthly'],
                        'additional_cost_yearly' => ($feature['additional_cost_monthly'] * 10), // 16.7% yearly discount
                        'is_billable' => $feature['is_billable'],
                        'is_metered' => $feature['is_metered'] ?? false,
                        'metered_rate' => $feature['metered_rate'] ?? 0,
                        'is_upgradeable' => true,
                        'is_downgradeable' => true,
                        'requires_approval' => $feature['requires_approval'] ?? false,
                        'is_overridable' => true,
                        'compliance_notes' => json_encode([
                            'gdpr_compliant' => true,
                            'data_processing_required' => $feature['feature_type'] === 'service'
                        ]),
                        'regional_restrictions' => null,
                        'stripe_meter_id' => null,
                        'stripe_price_id' => null,
                        'isactive' => true,
                        'sort_order' => 0,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime,
                    ];
                }
            }
        }

        if (!empty($insertData)) {
            DB::table('package_features')->insert($insertData);
        }
    }
}
