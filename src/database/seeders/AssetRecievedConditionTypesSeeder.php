<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetRecievedConditionTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'New', 'isactive'=>true],
            ['name' => 'Used' , 'isactive'=>true],
            ['name' => 'Refurbished' , 'isactive'=>true],
        ];

        foreach ($types as $type) {
            DB::table('asset_received_condition_types')->insert([
                'name' => $type['name'],
                'isactive'=>$type['isactive'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
