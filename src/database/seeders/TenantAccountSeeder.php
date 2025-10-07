<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Http\Request;
use App\Http\Controllers\UserAuthenticationController;
use App\Http\Requests\UserAuthRequest;
use Illuminate\Support\Facades\Validator;

class TenantAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $controller = new UserAuthenticationController();

        // Generate 10 tenant records
        for ($i = 1; $i <= 10; $i++) {
            $tenantData = [
                "user_name" => $faker->userName . "_tenant_" . $i,
                "password" => "Password@" . $i . "23!",  // Fixed: Added special character
                "name" => $faker->name,
                "portal_user_address" => $faker->address,
                "email" => "tenant" . $i . "@example.com",
                "portal_user_city" => $faker->city,
                "portal_user_country" => 39,
                "portal_user_zip_code" => "90060",
                "portal_contact_no" => "763901591",
                "portal_contact_no_code" => 39,
                "companycountry" => 39,
                "companyzip_code" => "90060", 
                "companycity" => $faker->city,
                "packageType" => "ENTERPRISE", // Valid option
                "package" => "Free",
                "companyname" => $faker->company,
                "companyemail" => "company" . $i . "@example.com", // Fixed: Use simple domain
                "companycontact_no" => 7893478373,
                "companycontact_no_code" => 39,
                "companyaddress" => $faker->address,
                "companywebsite" => "https://" . $faker->domainName, // Fixed: Added https://
                "invitedusers" => [
                    [
                        "name" => $faker->name,
                        "app_user_email" => "inviteduser" . $i . "_1@example.com", // Fixed: Unique emails
                        "admin" => $faker->boolean,
                        "accountPerson" => true,
                        "emailError" => ""
                    ],
                    [
                        "name" => $faker->name,
                        "app_user_email" => "inviteduser" . $i . "_2@example.com", // Fixed: Unique emails
                        "admin" => $faker->boolean,
                        "accountPerson" => false,
                        "emailError" => ""
                    ]
                ]
            ];

            try {
                // Create a request instance with the tenant data
                $request = new Request($tenantData);
                
                // Create a UserAuthRequest instance for validation
                $userAuthRequest = new UserAuthRequest();
                $userAuthRequest->replace($tenantData);
                
                // Call the controller method
                $response = $controller->registerNewUser($userAuthRequest);
                
                // Get the response data
                $responseData = json_decode($response->getContent(), true);
                
                // Check if the registration was successful based on the response structure
                if ($response->getStatusCode() === 201 && isset($responseData['status']) && $responseData['status'] === true) {
                    $this->command->info("✓ Tenant " . $i . " registered successfully: " . $tenantData['email']);
                    $this->command->info("  Response: " . $responseData['message']);
                } else {
                    $errorMessage = isset($responseData['message']) ? $responseData['message'] : 'Unknown error';
                    $this->command->error("✗ Failed to register tenant " . $i . ": " . $tenantData['email']);
                    $this->command->error("  Error: " . $errorMessage);
                }
                
            } catch (\Exception $e) {
                $this->command->error("✗ Error registering tenant " . $i . ": " . $e->getMessage());
                continue;
            }
        }

        $this->command->info("Tenant seeding completed!");
    }
}
