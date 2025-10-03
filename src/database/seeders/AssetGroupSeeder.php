<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Services\AssetGroupService;

class AssetGroupSeeder extends Seeder
{
    protected $tenantId;

    public function __construct()
    {

    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       $tenant_id = $this->tenantId ?? User::find(1)?->tenant_id;
        $registered_by = User::where('tenant_id', $this->tenantId)->first()?->id ?? 1;
        $current_time = now();
        $assetGroupService = app(AssetGroupService::class);

        // Multiple asset groups based on your data structure
        $assetGroups = [
            [
                'p_name' => 'Electronics Equipment',
                'p_thumbnail_image' => ([
                    [
                        'id' => 1,
                        'name' => 'electronics_thumbnail.jpg'
                    ]
                ]),
                'p_category' => 1, // Electronics category ID
                'p_sub_category' => 1, // Electronics sub-category ID
                'p_asset_details' => json_encode([]),
                'p_asset_classification' => null,
                'p_readings_parameters' => null,
                'p_asset_description' => 'Asset group for all electronic equipment and devices'
            ],
            [
                'p_name' => 'Vehicle Assets',
                'p_thumbnail_image' => ([
                    [
                        'id' => 2,
                        'name' => 'vehicle_thumbnail.jpg'
                    ]
                ]),
                'p_category' => 1, // Vehicle category ID
                'p_sub_category' => 2, // Vehicle sub-category ID
                'p_asset_details' => json_encode([]),
                'p_asset_classification' => null,
                'p_readings_parameters' => null,
                'p_asset_description' => 'Asset group for all vehicles and transportation equipment'
            ],
            [
                'p_name' => 'Computer Hardware',
                'p_thumbnail_image' => ([
                    [
                        'id' => 3,
                        'name' => 'computer_thumbnail.jpg'
                    ]
                ]),
                'p_category' => 1, // Computer category ID
                'p_sub_category' => 2, // Computer sub-category ID
                'p_asset_details' => json_encode([]),
                'p_asset_classification' => null,
                'p_readings_parameters' => null,
                'p_asset_description' => 'Asset group for computers, laptops, and IT hardware'
            ],
            [
                'p_name' => 'Office Furniture',
                'p_thumbnail_image' =>([
                    [
                        'id' => 4,
                        'name' => 'furniture_thumbnail.jpg'
                    ]
                ]),
                'p_category' => 1, // Furniture category ID
                'p_sub_category' => 3, // Furniture sub-category ID
                'p_asset_details' => json_encode([]),
                'p_asset_classification' => null,
                'p_readings_parameters' => null,
                'p_asset_description' => 'Asset group for office furniture and workspace equipment'
            ]
        ];

        foreach ($assetGroups as $assetGroup) {
            try {
                // Store the name for error messages before transforming the array
                $assetGroupName = $assetGroup['p_name'];
                
                // Add missing properties to the asset group data
                $assetGroup['p_tenant_id'] = $tenant_id;
                $assetGroup['p_registered_by'] = $registered_by;
                $assetGroup['p_current_time'] = $current_time;

                $result = $assetGroupService->insertAsset($assetGroup);

               if ($result['success']) {
                   echo("Asset group '{$assetGroupName}' created successfully.\n");
               } else {
                   echo("Failed to create asset group '{$assetGroupName}': " . ($result['message'] ?? 'Unknown error') . "\n");
               }
            } catch (\Exception $e) {
                echo("Error creating asset group '{$assetGroupName}': " . $e->getMessage() . "\n");
            }
        }
        echo "Asset groups seeded successfully.";
    }
}
