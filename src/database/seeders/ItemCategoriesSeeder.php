<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('item_categories')->insert([
            ['name' => 'Electronics', 'description' => 'Electronic gadgets and accessories', 'is_active' => true, 'tenant_id' => 1],
            ['name' => 'Furniture', 'description' => 'Home and office furniture', 'is_active' => true, 'tenant_id' => 1],
            ['name' => 'Clothing', 'description' => 'Men and Women clothing', 'is_active' => true, 'tenant_id' => 1],
        ]);
    }
}
