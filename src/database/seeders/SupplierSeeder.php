<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Faker\Factory as Faker;
use App\Services\SupplairService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $tenant_id = $this->tenantId ?? User::find(1)?->tenant_id;

        $faker = Faker::create();
        $supplierService = app(SupplairService::class); // Inject service if required

        for ($i = 0; $i < 10; $i++) {
            $data = [
                'p_name' => $faker->company,
                'p_address' => $faker->address,
                'p_supplier_description' => $faker->text(200),
                'p_supplier_type' => 'Company',
                // 'p_supplier_asset_classes' => [Str::random(10)],
                'p_asset_categories' => [
                    [
                        "id" => 1
                    ],
                    [
                        "id" => 2
                    ],
                   
                ],
                'p_supplier_rating' => $faker->numberBetween(1, 5),
                'p_supplier_bussiness_name' => $faker->company,
                'p_supplier_bussiness_register_no' => strtoupper(Str::random(10)),
                'p_supplier_primary_email' => $faker->companyEmail,
                'p_supplier_secondary_email' => $faker->companyEmail,
                'p_supplier_br_attachment' => [
                    [
                        "id" => 166,
                        "name" => "1749796570_b6539b9e-8d48-4c44-bc93-4f5998303a7f.doc",
                    ]
                ],
                'p_supplier_website' => $faker->url,
                'p_supplier_tel_no' => $faker->numerify('#########'), // 9-digit phone number
                'p_supplier_mobile' => $faker->numerify('7########'), // 9-digit mobile starting with 7
                'p_supplier_fax' => $faker->numerify('#########'), // 9-digit fax number
                'p_supplier_city' => $faker->city,
                'p_supplier_location_latitude' => (string) $faker->latitude,
                'p_supplier_location_longitude' => (string) $faker->longitude,
                'p_contact_no' => ["contact_no"=>$faker->numerify('7########'),"country_code"=>"39"], // 9-digit contact number
                'p_tenant_id' => $tenant_id,
                'p_current_time' => now(),
                'p_id' => null,
                'p_supplier_register_status' => 'APPROVED',
                'p_mobile_no_code' => 39,
                'p_contact_no_code' => 39,
                'p_country' => 39,
                'p_city' => $faker->city,
                'p_email' => $faker->email,
            ];


            $name = "johne doe";
            $data =  $supplierService->createOrUpdateSupplier($data, 1, $name);

            echo "Supplier created/updated: " . $data['message'] . "\n";
            // Log::debug('Supplier created/updated:', $data);
        }
    }
}
