<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('item_types')->insert([
            ['name' => 'Perishable', 'description' => 'Items with an expiration date', 'is_active' => true, 'tenant_id' => 1],
            ['name' => 'Non-Perishable', 'description' => 'Items without an expiration date', 'is_active' => true, 'tenant_id' => 1],
            ['name' => 'Digital', 'description' => 'Software and digital goods', 'is_active' => true, 'tenant_id' => 1],
        ]);
    }
}
