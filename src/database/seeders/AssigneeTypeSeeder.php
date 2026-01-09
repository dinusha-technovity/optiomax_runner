<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssigneeTypeSeeder extends Seeder
{
    public function run(): void
    {
        // DB::table('assignee_types')->truncate();
        DB::table('assignee_types')->upsert([
            [
                'id' => 1,
                'name' => 'Individual',
                'description' => 'Assignable to individual users.',
                'is_active' => true,
                'tenant_id' => null,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Groups',
                'description' => 'Assignable to groups or teams.',
                'is_active' => true,
                'tenant_id' => null,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'Both',
                'description' => 'Assignable to both individuals and groups.',
                'is_active' => true,
                'tenant_id' => null,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['id'], ['name', 'description', 'is_active', 'updated_at']);
    }
}