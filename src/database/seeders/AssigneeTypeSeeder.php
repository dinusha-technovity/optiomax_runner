<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssigneeTypeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('assignee_types')->truncate();
        DB::table('assignee_types')->insert([
            [
                'name' => 'Individual',
                'description' => 'Assignable to individual users.',
                'is_active' => true,
                'tenant_id' => null,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Groups',
                'description' => 'Assignable to groups or teams.',
                'is_active' => true,
                'tenant_id' => null,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Both',
                'description' => 'Assignable to both individuals and groups.',
                'is_active' => true,
                'tenant_id' => null,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}