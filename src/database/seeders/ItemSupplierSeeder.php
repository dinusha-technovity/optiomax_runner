<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemSupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

     protected $tenantId;

     public function __construct()
     {
         // Retrieve the selected user name from the service container
         $this->tenantId = app()->make('selectedTenantId');
     }
    public function run(): void
    {
        DB::table('item_suppliers')->insert([
            [
                'name' => 'Supplier One',
                'organization_name' => 'Supplier One Organization',
                'email' => 'supplier1@example.com',
                'phone' => '123-456-7890',
                'address' => '123 Supplier St, City, Country',
                'city' => 'CityOne',
                'country' => 'CountryOne',
                'tenant_id' => $this->tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Supplier Two',
                'organization_name' => 'Supplier Two Organization',
                'email' => 'supplier2@example.com',
                'phone' => '123-456-7891',
                'address' => '456 Supplier Ave, City, Country',
                'city' => 'CityTwo',
                'country' => 'CountryTwo',
                'tenant_id' => $this->tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Supplier Three',
                'organization_name' => 'Supplier Three Organization',
                'email' => 'supplier3@example.com',
                'phone' => '123-456-7892',
                'address' => '789 Supplier Blvd, City, Country',
                'city' => 'CityThree',
                'country' => 'CountryThree',
                'tenant_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Supplier Four',
                'organization_name' => 'Supplier Four Organization',
                'email' => 'supplier4@example.com',
                'phone' => '123-456-7893',
                'address' => '101 Supplier Ln, City, Country',
                'city' => 'CityFour',
                'country' => 'CountryFour',
                'tenant_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Supplier Five',
                'organization_name' => 'Supplier Five Organization',
                'email' => 'supplier5@example.com',
                'phone' => '123-456-7894',
                'address' => '202 Supplier Dr, City, Country',
                'city' => 'CityFive',
                'country' => 'CountryFive',
                'tenant_id' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Supplier Six',
                'organization_name' => 'Supplier Six Organization',
                'email' => 'supplier6@example.com',
                'phone' => '123-456-7895',
                'address' => '303 Supplier St, City, Country',
                'city' => 'CitySix',
                'country' => 'CountrySix',
                'tenant_id' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Supplier Seven',
                'organization_name' => 'Supplier Seven Organization',
                'email' => 'supplier7@example.com',
                'phone' => '123-456-7896',
                'address' => '404 Supplier Ave, City, Country',
                'city' => 'CitySeven',
                'country' => 'CountrySeven',
                'tenant_id' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Supplier Eight',
                'organization_name' => 'Supplier Eight Organization',
                'email' => 'supplier8@example.com',
                'phone' => '123-456-7897',
                'address' => '505 Supplier Blvd, City, Country',
                'city' => 'CityEight',
                'country' => 'CountryEight',
                'tenant_id' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
