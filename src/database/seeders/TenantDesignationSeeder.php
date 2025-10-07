<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantDesignationSeeder extends Seeder
{
    protected $tenantId;

    public function __construct()
    {
        // Retrieve the selected user name from the service container
        $this->tenantId = app()->make('selectedTenantId');
    }

    public function run(): void
    {
        // $requestTypes = [
        //     ['designation' => 'Chief Executive Officer'],
        //     ['designation' => 'Chief Operating Officer'],
        //     ['designation' => 'Marketing Manager'], 
        //     ['designation' => 'Humen Resource Manager'],
        //     ['designation' => 'Product Manager'],
        // ];
        $requestTypes = [
            [
                'designation' => 'System Admin',
                'tenant_id' => $this->tenantId,
            ],
            [
                'designation' => 'Chief Executive Officer',
                'tenant_id' => $this->tenantId,
            ],
            [
                'designation' => 'Chief Operating Officer',
                'tenant_id' => $this->tenantId,
            ],
            [
                'designation' => 'Marketing Manager',
                'tenant_id' => $this->tenantId,
            ],
            [
                'designation' => 'Humen Resource Manager',
                'tenant_id' => $this->tenantId,
            ],
            [
                'designation' => 'Product Manager',
                'tenant_id' => $this->tenantId,
            ],
        ];

        DB::table('designations')->insert($requestTypes);

        // Retrieve the ID of 'System Admin' designation
        $systemAdminDesignationId = DB::table('designations')
            ->where('designation', 'System Admin')
            ->where('tenant_id', $this->tenantId)
            ->value('id'); // Get the 'id' column value

        // Update the 'designationsId' column for users whose tenant_id matches the current tenant
        DB::table('users')
            ->where('tenant_id', $this->tenantId)
            ->update(['designation_id' => $systemAdminDesignationId]);
    }
}