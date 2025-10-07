<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MeasurementsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('measurements')->insert([
            ['name' => 'Kilogram', 'symbol' => 'kg', 'measurement_type' => 'Weight', 'is_active' => true],
            ['name' => 'Liter', 'symbol' => 'L', 'measurement_type' => 'Volume', 'is_active' => true],
            ['name' => 'Meter', 'symbol' => 'm', 'measurement_type' => 'Length', 'is_active' => true],
            ['name' => 'Piece', 'symbol' => 'pcs', 'measurement_type' => 'Unit', 'is_active' => true],
        ]);
    }
}
