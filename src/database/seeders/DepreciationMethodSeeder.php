<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepreciationMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        //delete existing records
        DB::table('depreciation_method_table')->truncate();
        
         $methods = [
            ['id'=>1, 'name' => 'Straightline' , 'isactive'=>true, 'slug' => 'straightline', 'multiplier' => 1.00, 'is_accelerated' => false, 'requires_units' => false],
            ['id'=>2, 'name' => 'Declining Balance' , 'isactive'=>true, 'slug' => 'declining-balance', 'multiplier' => 2.00, 'is_accelerated' => false, 'requires_units' => false],
            ['id'=>3, 'name' => 'Sum of the Years Digits' , 'isactive'=>true, 'slug' => 'sum-of-the-years-digits', 'multiplier' => 2.00, 'is_accelerated' => false, 'requires_units' => false],
            ['id'=>4, 'name' => 'Units of Production' , 'isactive'=>true, 'slug' => 'units-of-production', 'multiplier' => 2.00, 'is_accelerated' => false, 'requires_units' => true],
            // not yet implemented
            ['id'=>5, 'name' => 'Acceleration' , 'isactive'=>false, 'slug' => 'acceleration', 'multiplier' => 1.50, 'is_accelerated' => true, 'requires_units' => false],
            ['id'=>6, 'name' => 'Double Declining Balance' , 'isactive'=>false, 'slug' => 'double-declining-balance', 'multiplier' => 2.00, 'is_accelerated' => true, 'requires_units' => false],
            ['id'=>7, 'name' => 'Modified Accelerated Cost Recovery System (MACRS)' , 'isactive'=>false, 'slug' => 'macrs', 'multiplier' => 2.00, 'is_accelerated' => true, 'requires_units' => false],
            ['id'=>8, 'name' => 'Group Depreciation' , 'isactive'=>false, 'slug' => 'group-depreciation', 'multiplier' => 1.00, 'is_accelerated' => false, 'requires_units' => false],
            ['id'=>9, 'name' => 'Composite Depreciation' , 'isactive'=>false, 'slug' => 'composite-depreciation', 'multiplier' => 1.00, 'is_accelerated' => false, 'requires_units' => false],

        ];

        foreach ($methods as $method) {
            DB::table('depreciation_method_table')->insert([
                'name' => $method['name'],
                'isactive'=>$method['isactive'],
                'slug' => $method['slug'],
                'multiplier' => $method['multiplier'],
                'is_accelerated' => $method['is_accelerated'],
                'requires_units' => $method['requires_units'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
