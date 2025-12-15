<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class Tenant_RoleWidgetSeeder extends Seeder
{
    protected $tenantId;

    public function __construct()
    {
        // Retrieve the selected user tenant id from the service container when create a tenant
        if (app()->bound('selectedTenantId')) {
            $this->tenantId = app()->make('selectedTenantId');
        }
    }
    
    /**
     * Run the database seeds.
     * 
     * Assigns all widgets to the first role for the tenant.
     * Avoids duplicate entries.
     */
    public function run(): void
    {
        $tenant_id = $this->tenantId ?? User::find(1)?->tenant_id;

        if (!$tenant_id) {
            $this->command->warn('Tenant ID not found. Skipping role_widget seeding.');
            return;
        }

        // Get the first role for this tenant
        $role = DB::table('roles')
            ->where('tenant_id', $tenant_id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();

        if (!$role) {
            $this->command->warn("No role found for tenant_id={$tenant_id}. Skipping role_widget seeding.");
            return;
        }

        // Get all widgets
        $widgets = DB::table('app_widgets')->get();

        if ($widgets->isEmpty()) {
            $this->command->warn('No widgets found in app_widgets table.');
            return;
        }

        // Get existing role_widget entries for this role and tenant
        $existingWidgets = DB::table('role_widget')
            ->where('role_id', $role->id)
            ->where('tenant_id', $tenant_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->pluck('widget_id')
            ->toArray();

        $insertData = [];
        $skippedCount = 0;

        foreach ($widgets as $widget) {
            // Skip if already exists
            if (in_array($widget->id, $existingWidgets)) {
                $skippedCount++;
                continue;
            }

            $insertData[] = [
                'role_id' => $role->id,
                'widget_id' => $widget->id,
                'tenant_id' => $tenant_id,
                'settings' => null,
                'is_active' => true,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($insertData)) {
            DB::table('role_widget')->insert($insertData);
            $this->command->info('Successfully assigned ' . count($insertData) . " widgets to role_id={$role->id} (tenant_id={$tenant_id})");
        }

        if ($skippedCount > 0) {
            $this->command->info('Skipped ' . $skippedCount . ' widgets (already assigned)');
        }

        if (empty($insertData) && $skippedCount === 0) {
            $this->command->info('No widgets to assign.');
        }
    }
}
