<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetRequisitionAcquisitionTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //Upgrade, Replacement, New Purchase`
        $acquisitionTypes = [
            ['name' => 'Upgrade'],
            ['name' => 'Replacement'],
            ['name' => 'New Purchase'],
        ];
        foreach ($acquisitionTypes as $type) {
            DB::table('asset_requisition_acquisition_types')->insert($type);
        }
    }
}
