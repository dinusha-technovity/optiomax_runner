<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\CategoryAndSubCategoryService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategoryWithSubcategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant_id = $this->tenantId ?? User::find(1)?->tenant_id;
        
        $categoryWithSubCategoryService = app(CategoryAndSubCategoryService::class);
        
        // Sample asset categories data
        $categories = [
            [
                'categoriesName' => 'Electronics',
                'categoriesDiscription' => 'Electronic devices and equipment used in operations',
                'readingsParameters' => [
                    [
                        'parametersName' => 'Power Rating',
                        'parametersTag' => 'power_rating',
                        'dataType' => 'String'
                    ],
                    [
                        'parametersName' => 'Operating Temperature',
                        'parametersTag' => 'operating_temp',
                        'dataType' => 'String'
                    ]
                ],
                'asset_type' => 1
            ],
            [
                'categoriesName' => 'Machinery',
                'categoriesDiscription' => 'Industrial machinery and equipment for manufacturing',
                'readingsParameters' => [
                    [
                        'parametersName' => 'Machine Hours',
                        'parametersTag' => 'machine_hours',
                        'dataType' => 'String'
                    ],
                    [
                        'parametersName' => 'Maintenance Cycle',
                        'parametersTag' => 'maintenance_cycle',
                        'dataType' => 'String'
                    ]
                ],
                'asset_type' => 1
            ],
            [
                'categoriesName' => 'Vehicles',
                'categoriesDiscription' => 'Transportation vehicles and fleet management',
                'readingsParameters' => [
                    [
                        'parametersName' => 'Mileage',
                        'parametersTag' => 'mileage',
                        'dataType' => 'String'
                    ],
                    [
                        'parametersName' => 'Fuel Consumption',
                        'parametersTag' => 'fuel_consumption',
                        'dataType' => 'String'
                    ]
                ],
                'asset_type' => 1
            ],
            [
                'categoriesName' => 'IT Equipment',
                'categoriesDiscription' => 'Computer hardware and IT infrastructure',
                'readingsParameters' => [
                    [
                        'parametersName' => 'CPU Usage',
                        'parametersTag' => 'cpu_usage',
                        'dataType' => 'String'
                    ],
                    [
                        'parametersName' => 'Memory Usage',
                        'parametersTag' => 'memory_usage',
                        'dataType' => 'String'
                    ]
                ],
                'asset_type' => 1
            ],
            [
                'categoriesName' => 'Office Furniture',
                'categoriesDiscription' => 'Furniture and fixtures for office spaces',
                'readingsParameters' => [
                    [
                        'parametersName' => 'Condition Rating',
                        'parametersTag' => 'condition_rating',
                        'dataType' => 'String'
                    ]
                ],
                'asset_type' => 1
            ]
        ];

        foreach ($categories as $categoryData) {
            // Prepare data for the service
            $data = [
                'categoriesName' => $categoryData['categoriesName'],
                'categoriesDiscription' => $categoryData['categoriesDiscription'],
                'readingsParameters' => $categoryData['readingsParameters'],
                'asset_type' => $categoryData['asset_type'],
                'tenant_id' => $tenant_id,
                'current_time' => now()
            ];

            try {
                $result = $categoryWithSubCategoryService->createAssetCategories($data);
                
                if ($result['success']) {
                } else {
                    echo "✗ Failed to create asset category '{$categoryData['categoriesName']}': {$result['message']}\n";
                }
            } catch (\Exception $e) {
                echo "✗ Error creating asset category '{$categoryData['categoriesName']}': {$e->getMessage()}\n";
            }
        }
        echo "✓ Asset categories seeder run successfully.\n";

        // --------------------------------Sample subcategories data -----------------------------------------------

        $subcategories = [
            [
                "categoriesName" => "Circuit Breakers",
                "categoriesDiscription" => "Electrical circuit protection devices used in power distribution",
                "AssetCategoryId" => 1,
                "readingsParameters" => [
                    [
                        "parametersName" => "Use Hours",
                        "parametersTag" => "use_hours",
                        "dataType" => "String",
                        "units" => "Hours"
                    ],
                    [
                        "parametersName" => "Operating Voltage",
                        "parametersTag" => "operating_voltage",
                        "dataType" => "String",
                        "units" => "Volts"
                    ]
                ]
            ],
            [
                "categoriesName" => "Laptops",
                "categoriesDiscription" => "Portable computing devices for office work",
                "AssetCategoryId" => 1,
                "readingsParameters" => [
                    [
                        "parametersName" => "Battery Life",
                        "parametersTag" => "battery_life",
                        "dataType" => "String",
                        "units" => "Hours"
                    ],
                    [
                        "parametersName" => "Screen Time",
                        "parametersTag" => "screen_time",
                        "dataType" => "String",
                        "units" => "Hours"
                    ]
                ]
            ],
            [
                "categoriesName" => "Smartphones",
                "categoriesDiscription" => "Mobile communication devices for business use",
                "AssetCategoryId" => 1,
                "readingsParameters" => [
                    [
                        "parametersName" => "Battery Percentage",
                        "parametersTag" => "battery_percentage",
                        "dataType" => "String",
                        "units" => "Percentage"
                    ],
                    [
                        "parametersName" => "Storage Used",
                        "parametersTag" => "storage_used",
                        "dataType" => "String",
                        "units" => "GB"
                    ]
                ]
            ],
            [
                "categoriesName" => "Printers",
                "categoriesDiscription" => "Document printing and scanning equipment",
                "AssetCategoryId" => 1,
                "readingsParameters" => [
                    [
                        "parametersName" => "Pages Printed",
                        "parametersTag" => "pages_printed",
                        "dataType" => "String",
                        "units" => "Pages"
                    ],
                    [
                        "parametersName" => "Toner Level",
                        "parametersTag" => "toner_level",
                        "dataType" => "String",
                        "units" => "Percentage"
                    ]
                ]
            ],
            [
                "categoriesName" => "Servers",
                "categoriesDiscription" => "Network server hardware for data processing",
                "AssetCategoryId" => 1,
                "readingsParameters" => [
                    [
                        "parametersName" => "CPU Load",
                        "parametersTag" => "cpu_load",
                        "dataType" => "String",
                        "units" => "Percentage"
                    ],
                    [
                        "parametersName" => "Disk Usage",
                        "parametersTag" => "disk_usage",
                        "dataType" => "String",
                        "units" => "GB"
                    ]
                ]
            ]
        ];

        foreach ($subcategories as $categoryData) {
            // Prepare data for the service
            $data = [
                'categoriesName' => $categoryData['categoriesName'],
                'categoriesDiscription' => $categoryData['categoriesDiscription'],
                'readingsParameters' => $categoryData['readingsParameters'],
                'AssetCategoryId' => $categoryData['AssetCategoryId'],
                'tenant_id' => $tenant_id,
                'current_time' => now()
            ];

            try {
                $result = $categoryWithSubCategoryService->createAssetSubCategories($data);
                
                if ($result['success']) {
                } else {
                    echo "✗ Failed to create asset Sub-category '{$categoryData['categoriesName']}': {$result['message']}\n";
                }
            } catch (\Exception $e) {
                echo "✗ Error creating asset Sub-category '{$categoryData['categoriesName']}': {$e->getMessage()}\n";
            }
        }
        echo "✓ Asset sub-categories seeder run successfully.\n";

        echo "✓ Generated " . count($subcategories) . " subcategories data for seeding.\n";

    }
}
