<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $tenant_id = $this->tenantId ?? User::find(1)?->tenant_id;
        $organizationService = app(OrganizationService::class);


        $organizationData = [
            [
                "parent_node_id" => 0,
                "level" => 1,
                "data" => [
                    "organizationName" => "Optiomax Holdings",
                    "organizationDescription" => "This is descriptions",
                    "telephoneNumber" => "7612131545",
                    "address" => "132/1 Highlevel RD, Maharagama,Colombo, Sri Lanka",
                    "email" => "optiomax.optiomax@optiomax.lk",
                    "website" => "optiomax.com"
                ],
                "contact_no_code" => 39,
                "country" => null,
                "city" => null,
                "current_time" => now(),
                "relationship" => null,
                "tenant_id" => $tenant_id,
            ]
        ];


        $results = $organizationService->createOraganization($organizationData[0]);

        if ($results['success']) {
            echo "Organization Seeding successfully: " . $results['message'] . "\n";
        }
        else {
            echo "Failed to Seeding organization: " . $results['message'] . "\n";
        }
    }
}
