<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Fetch tenant IDs
        $tenantIds = DB::table('tenant_configuration')->pluck('tenant_id');

        $now = Carbon::now();

        $defaultSettings = [
            [
                'key' => 'currency',
                'value' => '1', // FK reference to currencies table
                'type' => 'integer',
                'category' => 'finance',
                'label' => 'System Currency',
                'description' => 'Default currency used across the system',
            ],
            [
                'key' => 'date_format',
                'value' => 'YYYY-MM-DD',
                'type' => 'string',
                'category' => 'general',
                'label' => 'Date Format',
                'description' => 'Default date format used system-wide',
            ],
            [
                'key' => 'time_format',
                'value' => 'HH24:MI',
                'type' => 'string',
                'category' => 'general',
                'label' => 'Time Format',
                'description' => 'Default time format used system-wide',
            ],
            [
                'key' => 'finance_email',
                'value' => '',
                'type' => 'string',
                'category' => 'finance',
                'label' => 'Finance Department Email',
                'description' => 'Primary finance department email address',
            ],
            // [
            //     'key' => 'po_limit',
            //     'value' => '100000',
            //     'type' => 'number',
            //     'category' => 'finance',
            //     'label' => 'Purchase Order Limit',
            //     'description' => 'Default maximum purchase order value',
            // ],
            [
                'key' => 'enable_notifications',
                'value' => 'true',
                'type' => 'boolean',
                'category' => 'general',
                'label' => 'Enable Notifications',
                'description' => 'Enable or disable system notifications',
            ],
        ];

        foreach ($tenantIds as $tenantId) {
            foreach ($defaultSettings as $setting) {
                DB::table('system_settings')->updateOrInsert(
                    [
                        'tenant_id' => $tenantId,
                        'key' => $setting['key'],
                    ],
                    [
                        'value' => $setting['value'],
                        'type' => $setting['type'],
                        'category' => $setting['category'],
                        'label' => $setting['label'],
                        'description' => $setting['description'],
                        'metadata' => null,
                        'is_default' => true,
                        'is_editable' => true,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }
}
