<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\AssetMasterService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AssetMasterSeeder extends Seeder
{

    // public function __construct(
    //     protected \App\Services\AssetsItemManagementService $AssetsItemManagementService
    // ) {
    //     // Constructor code if needed
    // }
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info("Starting AssetMasterSeeder...");
        Log::info("AssetMasterSeeder: Starting asset seeding process");

        $tenant_id = $this->tenantId ?? User::find(1)?->tenant_id;
        
        if (!$tenant_id) {
            $this->command->error("No tenant ID found. Cannot proceed with seeding.");
            Log::error("AssetMasterSeeder: No tenant ID found");
            return;
        }

        $this->command->info("Using tenant ID: " . $tenant_id);
        Log::info("AssetMasterSeeder: Using tenant ID: " . $tenant_id);

        try {
            $AssetsItemManagementService = app(AssetMasterService::class);
            $this->command->info("AssetMasterService instance created successfully");
            Log::info("AssetMasterSeeder: AssetMasterService instance created");
        } catch (\Exception $e) {
            $this->command->error("Failed to create AssetMasterService: " . $e->getMessage());
            Log::error("AssetMasterSeeder: Failed to create AssetMasterService", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return;
        }

        // Initialize counters
        $successCount = 0;
        $failedCount = 0;
        $totalAssets = 4998;

        $this->command->info("Processing " . $totalAssets . " assets for tenant " . $tenant_id . "...");

        for ($i = 1; $i <= $totalAssets; $i++) {
            $depreciationMethod = rand(1, 4);
            $warrantyConditionTypeId = rand(1, 3);

            $asset = [
                'asset_id' => 5,
                'serialNumber' => 'SN-' . strtoupper(Str::random(6)),
                'assetTag' => 'AT-' . strtoupper(Str::random(5)),
                'modelNumber' => rand(1000, 9999),
                'purchaseType' => rand(1, 4),
                'purchaseOrderNumber' => 'PO-' . rand(100, 999),
                'purchaseCost' => rand(10000, 100000),
                'purchaseCostCurrId' => 1,
                'receivedConditionId' => rand(1, 3),
                'warranty' => 'WRT-' . rand(100, 999),
                'warrantyConditionTypeId' => $warrantyConditionTypeId,
                'warrantyExpirerDate' => Carbon::now()->addYear()->format('Y-m-d'),
                'warrantyUsageName' => $warrantyConditionTypeId >= 2 ? 'Hours' : null,
                'warrantyUsageValue' => $warrantyConditionTypeId >= 2 ? rand(1000, 5000) : null,
                'itemValue' => rand(1000, 5000),
                'itemValueCurrId' => 1,
                'expectedLifeTime' => in_array($depreciationMethod, [1, 2, 3]) ? rand(5, 10) : null,
                'expectedLifeTimeUnit' => in_array($depreciationMethod, [1, 2, 3]) ? rand(1, 3) : null,
                'estimatedDepreciationValue' => in_array($depreciationMethod, [1, 2, 3]) ? rand(10, 90) : null,
                'salvage_value' => in_array($depreciationMethod, [1, 2, 3]) ? rand(500, 2000) : null,
                'depreciationStartDate' => Carbon::now()->format('Y-m-d'),
                'declineRate' => $depreciationMethod === 2 ? rand(10, 70) : null,
                'insuranceNumber' => 'INS-' . rand(100, 999),
                'insuranceExpirerDate' => Carbon::now()->addYears(3)->format('Y-m-d'),
                'responsiblePersonId' => 1,
                'departmentId' => 1,
                'location' => json_encode([
                    'latitude' => 6.933754 + (rand(-1000, 1000) / 10000),
                    'longitude' => 79.845608 + (rand(-1000, 1000) / 10000)
                ]),
                'asset_category_id' => 1,
                'asset_sub_category_id' => 1,
                'otherPurchaseDetails' => fake()->sentence(),
                'supplier' => 1,
                'Manufacturer' => 'Manufacturer ' . strtoupper(Str::random(3)),
                'reading_parameters' => json_encode([
                    [
                        "parametersName" => "asset group param",
                        "parametersTag" => "asset_group_param",
                        "dataType" => ["id" => 1, "name" => "String", "label" => "Alphanumeric"],
                        "units" => ["id" => 1, "name" => "Percentage", "label" => "Percentage (%)"]
                    ]
                ]),
                'depreciationMethod' => $depreciationMethod,
                'service_support_kpi' => json_encode([]),
                'maintenance_kpi' => json_encode([
                    ["details" => "Check-up " . rand(1, 10)]
                ]),
                'consumables_kpi' => json_encode([]),
                'asset_requisition_item_id' => null,
                'asset_requisition_id' => null,
                'procurement_id' => null,
                'registered_by_user_id' => 1,
                'tenant_id' => $tenant_id,
                'current_time' => now(),
                'thumbnailImages' => json_encode([
                    [
                        "id" => 1,
                        "name" => "1754045663_360_F_324557686_yIP0EDvln2zZbglmcakqmTxzdTE5t57h.jpg"
                    ]
                ]),

                'purchaseDocumentIds' => json_encode([
                    [
                        "id" => 1,
                        "name" => "1754045663_360_F_324557686_yIP0EDvln2zZbglmcakqmTxzdTE5t57h.jpg"
                    ]
                ]),

                'insuranceDocumentIds' => json_encode([
                    [
                        "id" => 1,
                        "name" => "1754045663_360_F_324557686_yIP0EDvln2zZbglmcakqmTxzdTE5t57h.jpg"
                    ]
                ]),
                'itemDocumentIds' => json_encode([
                    [
                        "id" => 1,
                        "name" => "1754045663_360_F_324557686_yIP0EDvln2zZbglmcakqmTxzdTE5t57h.jpg"
                    ]
                ]),

                'assetTags' => json_encode([
                    [
                        "id" => 1,
                        "label" => "man",
                        "children" => []
                    ]
                ]),

            ];

            //insert into asset item management service function
            $assetId = $AssetsItemManagementService->createAssetItemsRegister($asset);
            
            // Show progress every 100 assets
            if ($i % 100 == 0) {
                $this->command->info("Processed " . $i . "/" . $totalAssets . " assets - Success: " . $successCount . ", Failed: " . $failedCount);
            }

            // Check response and update counters
            try {
                if (isset($assetId['success']) && $assetId['success']) {
                    $successCount++;
                    
                    // Log first few successes for debugging
                    if ($i <= 5) {
                        $this->command->info("✓ Asset #" . $i . " created successfully: " . $assetId['message']);
                        Log::info("AssetMasterSeeder: Asset created successfully", [
                            'asset_number' => $i,
                            'tenant_id' => $tenant_id,
                            'serialNumber' => $asset['serialNumber'],
                            'message' => $assetId['message']
                        ]);
                    }
                } else {
                    $failedCount++;
                    $errorMessage = isset($assetId['message']) ? $assetId['message'] : 'Unknown error';
                    
                    // Log first few failures for debugging
                    if ($failedCount <= 5) {
                        $this->command->error("✗ Asset #" . $i . " failed: " . $errorMessage);
                        Log::error("AssetMasterSeeder: Asset creation failed", [
                            'asset_number' => $i,
                            'tenant_id' => $tenant_id,
                            'serialNumber' => $asset['serialNumber'],
                            'error' => $errorMessage
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $failedCount++;
                
                if ($failedCount <= 5) {
                    $this->command->error("✗ Exception for asset #" . $i . ": " . $e->getMessage());
                    Log::error("AssetMasterSeeder: Exception during asset processing", [
                        'asset_number' => $i,
                        'tenant_id' => $tenant_id,
                        'serialNumber' => $asset['serialNumber'],
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }

        // Final summary report
        $this->command->info("=== ASSET SEEDING COMPLETED ===");
        $this->command->info("Tenant ID: " . $tenant_id);
        $this->command->info("Total Assets Processed: " . $totalAssets);
        $this->command->info("✓ Successful Migrations: " . $successCount);
        $this->command->info("✗ Failed Migrations: " . $failedCount);
        $this->command->info("Success Rate: " . round(($successCount / $totalAssets) * 100, 2) . "%");
        
        // Log final summary
        Log::info("AssetMasterSeeder: Final Summary", [
            'tenant_id' => $tenant_id,
            'total_processed' => $totalAssets,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'success_rate' => round(($successCount / $totalAssets) * 100, 2)
        ]);

        if ($successCount > 0) {
            $this->command->info("🎉 Asset seeding completed successfully!");
        } else {
            $this->command->error("❌ All asset creations failed. Please check the logs for details.");
        }
    }

    //NOTE: This is for development purpose only- for newly created Tenants we can run this seeder to seed the data

    // php artisan tenants:seed tenant_ CategoryWithSubcategorySeeder
    // php artisan tenants:seed tenant_ OrganizationSeeder
    // php artisan tenants:seed tenant_ AssetGroupSeeder
    // php artisan tenants:seed tenant_ SupplierSeeder
    // php artisan tenants:seed tenant_ AssetMasterSeeder
}
