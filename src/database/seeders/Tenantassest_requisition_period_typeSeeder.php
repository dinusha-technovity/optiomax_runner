<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Tenantassest_requisition_period_typeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $period_types = [
            ['id' => 1, 'name' => 'Custom', 'description' => 'test', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Permanent', 'description' => 'test', 'created_at' => now(), 'updated_at' => now()],
        ];

        $targetIds = [1, 2];
        // Delete any rows not part of the target set, then upsert
        DB::transaction(function () use ($period_types, $targetIds) {
            DB::table('asset_requisition_period_types')
                ->whereNotIn('id', $targetIds)
                ->delete();

            DB::table('asset_requisition_period_types')
                ->upsert($period_types, ['id'], ['name', 'description', 'updated_at']);
        });
    }
}