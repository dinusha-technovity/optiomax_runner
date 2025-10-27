<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        DB::table('tax_master')->truncate();
        $percentageTaxes = [
            [
                'tax_code' => 'VAT',
                'tax_name' => 'Value Added Tax',
                'tax_type' => 'PERCENTAGE',
                'rate' => 15.0000,
                'amount' => 0,
                'is_compound' => false,
                'compound_on' => null,
                'applicable_to' => 'ASSET_PURCHASE',
                'jurisdiction' => 'National',
                'tax_authority' => 'Department of Inland Revenue',
                'calculation_order' => 1,
                'effective_to' => null,
                'status' => 'ACTIVE',
                'isactive' => true,
                'created_by' => 1,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tax_code' => 'NBT',
                'tax_name' => 'Nation Building Tax',
                'tax_type' => 'PERCENTAGE',
                'rate' => 2.0000,
                'amount' => 0,
                'is_compound' => true,
                'compound_on' => json_encode(['VAT']),
                'applicable_to' => 'ASSET_PURCHASE',
                'jurisdiction' => 'National',
                'tax_authority' => 'Department of Inland Revenue',
                'calculation_order' => 2,
                'effective_to' => null,
                'status' => 'ACTIVE',
                'isactive' => true,
                'created_by' => 1,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tax_code' => 'ESC',
                'tax_name' => 'Economic Service Charge',
                'tax_type' => 'PERCENTAGE',
                'rate' => 0.5000,
                'amount' => 0,
                'is_compound' => false,
                'compound_on' => null,
                'applicable_to' => 'MAINTENANCE',
                'jurisdiction' => 'National',
                'tax_authority' => 'Department of Inland Revenue',
                'calculation_order' => 3,
                'effective_to' => null,
                'status' => 'ACTIVE',
                'isactive' => true,
                'created_by' => 1,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

         DB::table('tax_master')->insert($percentageTaxes);
    }
}
