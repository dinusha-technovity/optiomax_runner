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
                'category_name' => 'Add Documents to Asset Category Form',
                'description' => 'Submit documents to asset category form',
                'category_tag' => 'ASSET_CATEGORY',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 2
            [
                'id' => 2,
                'category_name' => 'Add Documents to Asset Groups Form',
                'description' => 'Submit documents to asset groups form',
                'category_tag' => 'ASSET_GROUPS_CREATE',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 3
            [
                'id' => 3,
                'category_name' => 'Add Documents to Asset Items Form',
                'description' => 'Submit documents to asset items form',
                'category_tag' => 'ASSET_ITEMS_CREATE',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 4
            [
                'id' => 4,
                'category_name' => 'Add Documents to Asset Requisitions Form',
                'description' => 'Submit documents to asset requisitions form',
                'category_tag' => 'ASSET_REQUISITIONS_',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 5
            [
                'id' => 5,
                'category_name' => 'Add Documents to Procurement Initiate Form',
                'description' => 'Submit documents to procurement initiate form',
                'category_tag' => 'PROCURENMENT_INITIATE',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 6
            [
                'id' => 6,
                'category_name' => 'Add Documents to Staff Form',
                'description' => 'Submit documents to staff form',
                'category_tag' => 'STAFF',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 7
            [
                'id' => 7,
                'category_name' => 'Add Documents to Sub Asset Category Form',
                'description' => 'Submit documents to sub asset category form',
                'category_tag' => 'SUB_ASSET_CATEGORY',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 8
            [
                'id' => 8,
                'category_name' => 'Add Documents to Supplier Form',
                'description' => 'Submit documents to supplier form',
                'category_tag' => 'SUPPLIER',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 9
            [
                'id' => 9,
                'category_name' => 'Add Documents to Supplier Quotation Form',
                'description' => 'Submit documents to supplier quotation form',
                'category_tag' => 'SUPPLIER_QUOTATION',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 10
            [
                'id' => 10,
                'category_name' => 'Add Documents to System Configuration Form',
                'description' => 'Submit documents to system configuration form',
                'category_tag' => 'SYSTEM_CONFIGURATION',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 11
            [
                'id' => 11,
                'category_name' => 'Add Documents to Users Form',
                'description' => 'Submit documents to users form',
                'category_tag' => 'USERS_CREATE',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 12
            [
                'id' => 12,
                'category_name' => 'Add Documents to User Asset Items Form',
                'description' => 'Submit documents to user asset items form',
                'category_tag' => 'USER_ASSET_ITEMS',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 13
            [
                'id' => 13,
                'category_name' => 'Add Documents to Work Flow Form',
                'description' => 'Submit documents to work flow form',
                'category_tag' => 'WORK_FLOW',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 14
            [
                'id' => 14,
                'category_name' => 'Add Documents to Work Orders Form',
                'description' => 'Submit documents to work orders form',
                'category_tag' => 'CREATE_WORK_ORDERS',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 15
            [
                'id' => 15,
                'category_name' => 'Add Documents to Close Work Order Form',
                'description' => 'Submit documents to close work order form',
                'category_tag' => 'CLOSE_WORK_ORDER',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 16
            [
                'id' => 16,
                'category_name' => 'Add Documents to Item Master Form',
                'description' => 'Submit documents to item master form',
                'category_tag' => 'ITEM_MASTER_CREATE',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 17
            [
                'id' => 17,
                'category_name' => 'Add Documents to Incident Reports Form',
                'description' => 'Submit documents to incident reports form',
                'category_tag' => 'CREATE_INCIDENT_REPORTS',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 18
            [
                'id' => 18,
                'category_name' => 'Add Documents to Customer Form',
                'description' => 'Submit documents to customer form',
                'category_tag' => 'CUSTOMER_CREATE',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 19
            [
                'id' => 19,
                'category_name' => 'Add Documents to Asset Availability Form',
                'description' => 'Submit documents to asset availability form',
                'category_tag' => 'ASSET_AVAILABILITY',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 20
            [
                'id' => 20,
                'category_name' => 'Add Documents to Asset Booking Form',
                'description' => 'Submit documents to asset booking form',
                'category_tag' => 'ASSET_BOOKING',
                'isactive' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            // 21
            [
                'id' => 21,
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
