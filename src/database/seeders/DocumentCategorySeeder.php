<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DocumentCategorySeeder extends Seeder
{  
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('document_category')->upsert([
            // 1
            [
                'id' => 1,
                'category_name' => 'Asset Category',
                'description' => 'asset category form',
                'category_tag' => 'ASSET_CATEGORY',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 2
            [
                'id' => 2,
                'category_name' => 'Asset Groups Create',
                'description' => 'asset groups create form',
                'category_tag' => 'ASSET_GROUPS_CREATE',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 3
            [
                'id' => 3,
                'category_name' => 'Asset Items Create',
                'description' => 'asset items create form',
                'category_tag' => 'ASSET_ITEMS_CREATE',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 4
            [
                'id' => 4,
                'category_name' => 'Asset Requisitions',
                'description' => 'asset requisitions form',
                'category_tag' => 'ASSET_REQUISITIONS_',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 5
            [
                'id' => 5,
                'category_name' => 'Procurement Initiate',
                'description' => 'procurement initiate form',
                'category_tag' => 'PROCURENMENT_INITIATE',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 6
            [
                'id' => 6,
                'category_name' => 'Staff',
                'description' => 'staff form',
                'category_tag' => 'STAFF',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 7
            [
                'id' => 7,
                'category_name' => 'Sub Asset Category',
                'description' => 'sub asset category form',
                'category_tag' => 'SUB_ASSET_CATEGORY',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 8
            [
                'id' => 8,
                'category_name' => 'Supplier',
                'description' => 'supplier form',
                'category_tag' => 'SUPPLIER',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 9
            [
                'id' => 9,
                'category_name' => 'Supplier Quotation',
                'description' => 'supplier quotation form',
                'category_tag' => 'SUPPLIER_QUOTATION',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 10
            [
                'id' => 10,
                'category_name' => 'System Configuration',
                'description' => 'system configuration form',
                'category_tag' => 'SYSTEM_CONFIGURATION',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 11
            [
                'id' => 11,
                'category_name' => 'Users Create',
                'description' => 'users create form',
                'category_tag' => 'USERS_CREATE',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 12
            [
                'id' => 12,
                'category_name' => 'User Asset Items',
                'description' => 'user asset items form',
                'category_tag' => 'USER_ASSET_ITEMS',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 13
            [
                'id' => 13,
                'category_name' => 'Work Flow',
                'description' => 'work flow form',
                'category_tag' => 'WORK_FLOW',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 14
            [
                'id' => 14,
                'category_name' => 'Create Work Orders',
                'description' => 'create work orders form',
                'category_tag' => 'CREATE_WORK_ORDERS',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 15
            [
                'id' => 15,
                'category_name' => 'Close Work Order',
                'description' => 'close work order form',
                'category_tag' => 'CLOSE_WORK_ORDER',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 16
            [
                'id' => 16,
                'category_name' => 'Create Item Master',
                'description' => 'Create item master form',
                'category_tag' => 'ITEM_MASTER_CREATE',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 17
            [
                'id' => 17,
                'category_name' => 'Create Incident Reports',
                'description' => 'Create incident reports form',
                'category_tag' => 'CREATE_INCIDENT_REPORTS',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 18
            [
                'id' => 18,
                'category_name' => 'Customer create',
                'description' => 'Submit customer create form',
                'category_tag' => 'CUSTOMER_CREATE',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 19
            [
                'id' => 19,
                'category_name' => 'Asset Availability',
                'description' => 'Submit asset availability form',
                'category_tag' => 'ASSET_AVAILABILITY',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 20
            [
                'id' => 20,
                'category_name' => 'Asset Booking',
                'description' => 'Submit asset booking form',
                'category_tag' => 'ASSET_BOOKING',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 21
            [
                'id' => 21,
                'category_name' => 'Asset Master Bulk Data Import',
                'description' => 'asset master bulk data import form',
                'category_tag' => 'ASSET_MASTER_BULK_DATA_IMPORT',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 22
            [ 
                'id' => 22,
                'category_name' => 'Supplier Bulk Data Import',
                'description' => 'supplier bulk data import form',
                'category_tag' => 'SUPPLIER_BULK_DATA_IMPORT',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ], 
            // 23
            [ 
                'id' => 23,
                'category_name' => 'Customer Bulk Data Import',
                'description' => 'customer bulk data import form',
                'category_tag' => 'CUSTOMER_BULK_DATA_IMPORT',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 24
            [ 
                'id' => 24,
                'category_name' => 'Asset Category Bulk Data Import',
                'description' => 'asset category bulk data import form',
                'category_tag' => 'ASSET_CATEGORY_BULK_DATA_IMPORT',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 25
            [ 
                'id' => 25,
                'category_name' => 'Asset Sub Category Bulk Data Import',
                'description' => 'asset sub category bulk data import form',
                'category_tag' => 'ASSET_SUB_CATEGORY_BULK_DATA_IMPORT',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 26
            [ 
                'id' => 26,
                'category_name' => 'Asset Group Bulk Data Import',
                'description' => 'asset group bulk data import form',
                'category_tag' => 'ASSET_GROUP_BULK_DATA_IMPORT',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 27
            [ 
                'id' => 27,
                'category_name' => 'Items Master Bulk Data Import',
                'description' => 'items master bulk data import form',
                'category_tag' => 'ITEMS_MASTER_BULK_DATA_IMPORT',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 28
            [ 
                'id' => 28,
                'category_name' => 'Assets Availability Terms Type Bulk Data Import',
                'description' => 'assets availability terms type bulk data import form',
                'category_tag' => 'ASSETS_AVAILABILITY_TERMS_TYPE_BULK_DATA_IMPORT',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ], ['id'], ['category_name', 'description', 'category_tag', 'isactive', 'updated_at']); 
    } 
} 
