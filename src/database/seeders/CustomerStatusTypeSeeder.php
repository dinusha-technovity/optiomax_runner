<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerStatusTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customerStatusTypes = [
            [
                'name' => 'active',
                'description' => 'Customer is currently active and can perform transactions',
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'inactive',
                'description' => 'Customer is temporarily inactive',
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'blacklisted',
                'description' => 'Customer is blacklisted and restricted from services',
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'archived',
                'description' => 'Customer record is archived for historical purposes',
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('customer_status_types')->insert($customerStatusTypes);
    }
}
