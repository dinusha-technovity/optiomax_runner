<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WarrentyConditionTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('warrenty_condition_types')->truncate();

         $types = [
            ['name' => 'Time based', 'isactive'=>true],
            ['name' => 'Usage based', 'isactive'=>true],
            ['name' => 'Combined', 'isactive'=>true],
        ];

        foreach ($types as $type) {
            DB::table('warrenty_condition_types')->insert([
                'name' => $type['name'],
                'isactive'=>$type['isactive'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
