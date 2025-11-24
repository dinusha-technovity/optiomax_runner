<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
 
class TenantRoleHasPermissionsSeeder extends Seeder
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
     */
    public function run(): void
    {
        $tenant_id = $this->tenantId ?? User::find(1)?->tenant_id;

        // Retrieve the admin role
        $adminRole = Role::where('name', 'Super Admin')
            ->where('tenant_id', $tenant_id)
            ->first();

        // Retrieve all permissions
        $permissions = Permission::all();

        // // Assign all permissions to the admin role
        // if ($adminRole) {
        //     $adminRole->syncPermissions($permissions);
        // }

        // Assign all permissions to the Super Admin role
        if ($adminRole) {
            // Attach or sync all permissions to the role
            $adminRole->permissions()->sync($permissions->pluck('id')->toArray());
        }
    }
}
