<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemCategoryTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('item_category_type')->insert([
            ['name' => 'Consumables', 'description' => 'Items that are used up during operations', 'is_active' => true, 'tenant_id' => 1],
            ['name' => 'Spare Parts', 'description' => 'Replacement parts for equipment maintenance', 'is_active' => true, 'tenant_id' => 1],
        ]);
    }
}
