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
                'image_path' => "$img_directory/todo",
                'category_id' => $categoryIds[0],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 7, 'height' => 44]),
                'design_component' => "Todo List",
                'widget_type' => "TODO_LIST",
            ],
            // [
            //     'image_path' => "/assets/icons/widget_drawer/analytics-graph-chart-svgrepo-com.svg",
            //     'category_id' => $categoryIds[1],
            //     'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 7]),
            //     'design_component' => "<div>test</div>",
            //     'widget_type' => "LINE_CHART",
            // ],
            // [
            //     'image_path' => "images/widget_ui/total-assets.svg",
            //     'category_id' => $categoryIds[2],
            //     'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 7]),
            //     'design_component' => "<div>Hello</div>",
            //     'widget_type' => "BAR_CHART",
            // ],
            [
                'image_path' => "$img_directory/complete-work-orders",
                'category_id' => $categoryIds[0],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 5, 'height' => 26]),
                'design_component' => "Completed Work Orders",
                'widget_type' => "COMPLETED_WORK_ORDERS",
            ],
            [
                'image_path' => "$img_directory/total-asset-count",
                'category_id' => $categoryIds[3],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 12]),
                'design_component' => "Total Asset Count",
                'widget_type' => "TOTAL_ASSET_COUNT",
            ],
            [
                'image_path' => "$img_directory/requisition-status",
                'category_id' => $categoryIds[4],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 12]),
                'design_component' => "Requisition Status",
                'widget_type' => "REQUISITION_STATUS",
            ],
            [
                'image_path' => "$img_directory/maintenance-status",
                'category_id' => $categoryIds[5],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 14]),
                'design_component' => "Scheduled vs Maintenance",
                'widget_type' => "SCHEDULED_VS_MAINTENANCE",
            ],
            [
                'image_path' => "$img_directory/book-value-category",
                'category_id' => $categoryIds[6],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 12]),
                'design_component' => "Book Value of Category",
                'widget_type' => "BOOK_VALUE_OF_CATEGORY",
            ],
            [
                'image_path' => "$img_directory/end-of-life",
                'category_id' => $categoryIds[7],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 12]),
                'design_component' => "End of Life by Category",
                'widget_type' => "END_OF_LIFE_BY_CATEGORY",
            ],
            [
                'image_path' => "$img_directory/procurnment-status",
                'category_id' => $categoryIds[4],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 12]),
                'design_component' => "Procurement Status",
                'widget_type' => "PROCUREMENT_STATUS",
            ],
            [
                'image_path' => "$img_directory/work-order-status",
                'category_id' => $categoryIds[5],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 12]),
                'design_component' => "Work Orders Status",
                'widget_type' => "WORK_ORDERS_STATUS",
            ],
            [
                'image_path' => "$img_directory/depreciation-process",
                'category_id' => $categoryIds[6],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 8, 'height' => 18]),
                'design_component' => "Depreciation Progress",
                'widget_type' => "DEPRECIATION_PROGRESS",
            ],
            [
                'image_path' => "$img_directory/recomended-vendors",
                'category_id' => $categoryIds[8],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 8, 'height' => 18]),
                'design_component' => "Recommended Vendors",
                'widget_type' => "RECOMMENDED_VENDORS",
            ],
            [
                'image_path' => "$img_directory/asset-utilization",
                'category_id' => $categoryIds[9],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 15]),
                'design_component' => "Asset Utilization",
                'widget_type' => "ASSET_UTILIZATION",
            ],
            [
                'image_path' => "$img_directory/high-incident-asset",
                'category_id' => $categoryIds[10],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 12]),
                'design_component' => "High Incident Asset",
                'widget_type' => "HIGH_INCIDENT_ASSET",
            ],
            [
                'image_path' => "$img_directory/missed-maintenance",
                'category_id' => $categoryIds[11],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 12]),
                'design_component' => "Missed Maintenance",
                'widget_type' => "MISSED_MAINTENANCE",
            ],
            [
                'image_path' => "$img_directory/user-incidents",
                'category_id' => $categoryIds[5],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 14]),
                'design_component' => "User Incident Tasks",
                'widget_type' => "USER_INCIDENT_TASKS",
            ],
            [
                'image_path' => "$img_directory/requisition-inbox",
                'category_id' => $categoryIds[0],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 20]),
                'design_component' => "Approval Inbox",
                'widget_type' => "REQUISITION_INBOX",
            ],
            [
                'image_path' => "$img_directory/maintenance-alerts",
                'category_id' => $categoryIds[5],
                'design_obj' => json_encode(['x_value' => 0, 'y_value' => 0, 'width' => 4, 'height' => 14]),
                'design_component' => "Maintenance Alerts",
                'widget_type' => "MAINTENANCE_ALERTS",
            ],

        ];

        DB::table('app_widgets')->insert($itemLists);
    }
}