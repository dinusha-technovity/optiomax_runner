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
        $period_type = [
            [
                'name' => "Long period/terms",
                'description' => "test",
            ],
            [
                'name' => "Short period/terms",
                'description' => "test",
            ],
            [
                'name' => "Temporary",
                'description' => "test",
            ],
            [
                'name' => "Permanent",
                'description' => "test",
            ],
        ];

        // Seed multiple period_type
        foreach ($period_type as $Period_type) {
            DB::table('asset_requisition_period_types')->insert($Period_type);
        }
    }
}