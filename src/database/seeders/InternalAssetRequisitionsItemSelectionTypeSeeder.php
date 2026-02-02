<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InternalAssetRequisitionsItemSelectionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $selectionTypes = [
            [
                'name' => 'With Target Asset',
                'description' => 'Requisition specifies a particular asset to be allocated. The requester has identified a specific asset they need.',
                'isactive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Without Target Asset',
                'description' => 'Requisition does not specify a particular asset. Any available asset matching the item type can be allocated.',
                'isactive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('internal_asset_requisitions_item_selection_types')->insert($selectionTypes);
    }
}
