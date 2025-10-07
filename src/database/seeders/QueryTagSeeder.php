<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QueryTagSeeder extends Seeder
{ 
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define the query tag data
        $queryTag = [
            'name'  => 'get_supplier_type',
            'query' => 'SELECT supplier_type FROM suppliers WHERE id = $1::BIGINT',
            'type'  => 'query', // Assuming 'type' column exists
        ];

        // Insert or update existing record
        DB::table('query_tag')->updateOrInsert(
            ['name' => 'get_supplier_type'],  // Condition to check existing entry
            $queryTag                         // Data to insert/update
        );
    }
}
