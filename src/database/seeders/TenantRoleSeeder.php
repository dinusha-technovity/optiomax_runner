<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class TenantRoleSeeder extends Seeder
{
    protected $tenantId;

    public function __construct()
    {
        // Retrieve the selected user name from the service container
        $this->tenantId = app()->make('selectedTenantId');
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $Role = [
            [
                'name' => 'Super Admin',
                'description' => 'test',
                'tenant_id' => $this->tenantId,
            ],
        ];

        // Seed multiple permission
        foreach ($Role as $Role) {
            Role::create($Role);
        }
    }
}