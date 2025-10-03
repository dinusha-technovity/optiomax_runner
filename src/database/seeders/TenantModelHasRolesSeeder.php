<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;

class TenantModelHasRolesSeeder extends Seeder
{
    protected $tenantId;

    public function __construct()
    {
        // Retrieve the selected tenant ID from the service container
        $this->tenantId = app()->make('selectedTenantId');
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Retrieve users for the tenant (optionally filter system users in query)
        $users = User::where('tenant_id', $this->tenantId)->get();

        // Retrieve the 'Super Admin' role for the tenant
        $adminRole = Role::where('name', 'Super Admin')
                            ->where('tenant_id', $this->tenantId)
                            ->first();

        if ($adminRole) {
            foreach ($users as $user) {
                // Only assign if user is NOT a system user
                if ($user->is_system_user) {
                    continue;
                }
                // Attach the role to the user without detaching other roles
                $user->roles()->syncWithoutDetaching([$adminRole->id]);
            }

            $this->command->info('Super Admin role assigned to applicable users in tenant ID ' . $this->tenantId);
        } else {
            $this->command->warn('Super Admin role not found for tenant ID ' . $this->tenantId);
        }
    }
}