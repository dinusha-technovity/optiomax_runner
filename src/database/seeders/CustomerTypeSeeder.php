<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('customer_types')->truncate();
        $customerTypes = [
            [
                'name' => 'Individual',
                'description' => 'Individual customer type for personal accounts',
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Company',
                'description' => 'Company customer type for business accounts',
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Department',
                'description' => 'Department customer type for organizational units',
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Government',
                'description' => 'Government customer type for public sector accounts',
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('customer_types')->insert($customerTypes);
    }
}
