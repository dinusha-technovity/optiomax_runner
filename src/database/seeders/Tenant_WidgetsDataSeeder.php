<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Tenant_WidgetsDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('app_widgets_categories')->truncate();
        DB::table('app_widgets')->truncate();

        $categories = [
            ['category_name' => 'Activity'], //0
            ['category_name' => 'Analytics'], //1
            ['category_name' => 'Billings'], //2
            ['category_name' => 'Asset Overview Dashboard'], //3
            ['category_name' => 'Requisition & Procurement Dashboard'], //4
            ['category_name' => 'Maintenance Dashboard'], //5
            ['category_name' => 'Financial & Value Monitoring'], //6
            ['category_name' => 'Depreciation & Replacement Forecasting'], //7
            ['category_name' => 'Procurement Optimization'], //8
            ['category_name' => 'Utilisation & Idle Asset Alerts'], //9
            ['category_name' => 'Budget & Opex Forecasting'], //10
            ['category_name' => 'Compliance & Audit Flags'], //11
        ];

        $categoryIds = [];
        foreach ($categories as $category) {
            $categoryId = DB::table('app_widgets_categories')->insertGetId($category);
            $categoryIds[] = $categoryId;
        }

        $img_directory = '/images/widgets_ui';

        $itemLists = [
            [
                'id' => 1,
                'image_path' => "$img_directory/complete-work-orders",
                'category_id' => $categoryIds[0],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 5, 'height' => 26]),
                'design_component' => "Completed Work Orders",
                'widget_type' => "COMPLETED_WORK_ORDERS",
                'is_enable_for_web_app' => true,
                'is_enable_for_mobile_app' => true,
            ],
            [
                'id' => 2,
                'image_path' => "$img_directory/total-asset-count",
                'category_id' => $categoryIds[3],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 12]),
                'design_component' => "Total Asset Count",
                'widget_type' => "TOTAL_ASSET_COUNT",
                'is_enable_for_web_app' => true,
                'is_enable_for_mobile_app' => false,
            ],
            [
                'id' => 3,
                'image_path' => "$img_directory/requisition-status",
                'category_id' => $categoryIds[4],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 12]),
                'design_component' => "Requisition Status",
                'widget_type' => "REQUISITION_STATUS",
                'is_enable_for_web_app' => true,
                'is_enable_for_mobile_app' => false,
            ],
            [
                'id' => 4,
                'image_path' => "$img_directory/maintenance-status",
                'category_id' => $categoryIds[5],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 14]),
                'design_component' => "Scheduled vs Maintenance",
                'widget_type' => "SCHEDULED_VS_MAINTENANCE",
                'is_enable_for_web_app' => true,
                'is_enable_for_mobile_app' => false,
            ],
            [
                'id' => 5,
                'image_path' => "$img_directory/book-value-category",
                'category_id' => $categoryIds[6],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 12]),
                'design_component' => "Book Value of Category",
                'widget_type' => "BOOK_VALUE_OF_CATEGORY",
                'is_enable_for_web_app' => true,
                'is_enable_for_mobile_app' => false,
            ],
            [
                'id' => 6,
                'image_path' => "$img_directory/end-of-life",
                'category_id' => $categoryIds[7],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 12]),
                'design_component' => "End of Life by Category",
                'widget_type' => "END_OF_LIFE_BY_CATEGORY",
                'is_enable_for_web_app' => true,
                'is_enable_for_mobile_app' => false,
            ],
            [
                'id' => 7,
                'image_path' => "$img_directory/procurnment-status",
                'category_id' => $categoryIds[4],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 12]),
                'design_component' => "Procurement Status",
                'widget_type' => "PROCUREMENT_STATUS",
                'is_enable_for_web_app' => true,
                'is_enable_for_mobile_app' => false,
            ],
            [
                'id' => 8,
                'image_path' => "$img_directory/work-order-status",
                'category_id' => $categoryIds[5],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 12]),
                'design_component' => "Work Orders Status",
                'widget_type' => "WORK_ORDERS_STATUS",
                'is_enable_for_web_app' => true,
                'is_enable_for_mobile_app' => true,
            ],
            [
                'id' => 9,
                'image_path' => "$img_directory/depreciation-process",
                'category_id' => $categoryIds[6],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 8, 'height' => 18]),
                'design_component' => "Depreciation Progress",
                'widget_type' => "DEPRECIATION_PROGRESS",
                'is_enable_for_web_app' => true,
                'is_enable_for_mobile_app' => false,
            ],
            [
                'id' => 10,
                'image_path' => "$img_directory/recomended-vendors",
                'category_id' => $categoryIds[8],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 8, 'height' => 18]),
                'design_component' => "Recommended Vendors",
                'widget_type' => "RECOMMENDED_VENDORS",
                'is_enable_for_web_app' => true,
                'is_enable_for_mobile_app' => false,
            ],
            [
                'id' => 11,
                'image_path' => "$img_directory/asset-utilization",
                'category_id' => $categoryIds[9],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 15]),
                'design_component' => "Asset Utilization",
                'widget_type' => "ASSET_UTILIZATION",
                'is_enable_for_web_app' => true,
                'is_enable_for_mobile_app' => true,
            ],
            [
                'id' => 12,
                'image_path' => "$img_directory/high-incident-asset",
                'category_id' => $categoryIds[10],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 12]),
                'design_component' => "High Incident Asset",
                'widget_type' => "HIGH_INCIDENT_ASSET",
                'is_enable_for_web_app' => true,
                'is_enable_for_mobile_app' => false,
            ],
            [
                'id' => 13,
                'image_path' => "$img_directory/missed-maintenance",
                'category_id' => $categoryIds[11],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 12]),
                'design_component' => "Missed Maintenance",
                'widget_type' => "MISSED_MAINTENANCE",
                'is_enable_for_web_app' => true,
                'is_enable_for_mobile_app' => false,
            ],
            [
                'id' => 14,
                'image_path' => "$img_directory/user-incidents",
                'category_id' => $categoryIds[5],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 14]),
                'design_component' => "User Incident Tasks",
                'widget_type' => "USER_INCIDENT_TASKS",
                'is_enable_for_web_app' => true,
                'is_enable_for_mobile_app' => false,
            ],
            [
                'id' => 15,
                'image_path' => "$img_directory/requisition-inbox",
                'category_id' => $categoryIds[0],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 20]),
                'design_component' => "Approval Inbox",
                'widget_type' => "REQUISITION_INBOX",
                'is_enable_for_web_app' => true,
                'is_enable_for_mobile_app' => true,
            ],
            [
                'id' => 16,
                'image_path' => "$img_directory/maintenance-alerts",
                'category_id' => $categoryIds[5],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 14]),
                'design_component' => "Maintenance Alerts",
                'widget_type' => "MAINTENANCE_ALERTS",
                'is_enable_for_web_app' => true,
                'is_enable_for_mobile_app' => false,
            ],
        ];

        DB::table('app_widgets')->upsert(
            $itemLists,
            ['id'], // unique by id
            [
                'image_path',
                'category_id',
                'design_obj',
                'design_component',
                'widget_type',
                'is_enable_for_web_app',
                'is_enable_for_mobile_app'
            ]
        );
    }
}