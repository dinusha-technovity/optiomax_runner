<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Tbl_menuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $UserManagementID = DB::table('permissions')->where('name', 'User Management')->value('id');
        $RoleID = DB::table('permissions')->where('name', 'Role')->value('id');
        $UsersID = DB::table('permissions')->where('name', 'Users')->value('id');
        $ConfigID = DB::table('permissions')->where('name', 'Config')->value('id');
        $OrganizationID = DB::table('permissions')->where('name', 'Organization')->value('id');
        $WorkflowID = DB::table('permissions')->where('name', 'Workflow')->value('id');
        $ProcurementManagementID = DB::table('permissions')->where('name', 'Procurement Management')->value('id');
        $AssetRequisitionsID = DB::table('permissions')->where('name', 'Asset Requisitions')->value('id');
        $ProcurementInitiateID = DB::table('permissions')->where('name', 'Procurement Initiate')->value('id');
        $ProcurementStaffID = DB::table('permissions')->where('name', 'Procurement Staff')->value('id');
        $SupplierID = DB::table('permissions')->where('name', 'Supplier')->value('id');
        $SupplierQuotationID = DB::table('permissions')->where('name', 'Supplier Quotation')->value('id');
        $AssetsItemsID = DB::table('permissions')->where('name', 'Asset Items')->value('id');
        $AssetsManagementID = DB::table('permissions')->where('name', 'Asset Management')->value('id');
        $AssetCategoryReadingParametersID = DB::table('permissions')->where('name', 'Asset Category Reading Parameters')->value('id');
        $workOrderPermissionID = DB::table('permissions')->where('name', 'WorkOrders')->value('id');

        $currentTime = Carbon::now();

        DB::table('menu_list')->truncate(); //remove existing data and appending new
        DB::table('menu_list')->insert([
            [
                'permission_id' => $UserManagementID,
                'parent_id' => null,
                'menuname' => 'User Management',
                'description' => 'test',
                'menulink' => '#',
                'icon' => 'MdManageAccounts',
                'isconfiguration' => false,
                'menu_order' => 1,
                'isactive' => false,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $RoleID,
                'parent_id' => 1,
                'menuname' => 'User Roles',
                'description' => 'test',
                'menulink' => '/Roles',
                'icon' => null,
                'isconfiguration' => false,
                'menu_order' => 1,
                'isactive' => false,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $UsersID,
                'parent_id' => 1,
                'menuname' => 'User Accounts',
                'description' => 'test',
                'menulink' => '/users',
                'icon' => null,
                'isconfiguration' => false,
                'menu_order' => 2,
                'isactive' => false,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $ConfigID,
                'parent_id' => null,
                'menuname' => 'System Configuration',
                'description' => 'test',
                'menulink' => '#',
                'icon' => 'GrDocumentConfig',
                'isconfiguration' => true,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $SupplierID,
                'parent_id' => 4,
                'menuname' => 'Manage Suppliers',
                'description' => 'test',
                'menulink' => '/supplier',
                'icon' => null,
                'isconfiguration' => true,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $AssetCategoryReadingParametersID,
                'parent_id' => 4,
                'menuname' => 'Asset Categories',
                'description' => 'test',
                'menulink' => '/asset_categories',
                'icon' => null,
                'isconfiguration' => true,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $AssetCategoryReadingParametersID,
                'parent_id' => 4,
                'menuname' => 'Asset Sub Categories',
                'description' => 'test',
                'menulink' => '/sub_asset_categories',
                'icon' => null,
                'isconfiguration' => true,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $OrganizationID,
                'parent_id' => 4,
                'menuname' => 'Build Organization',
                'description' => 'test',
                'menulink' => '/organization',
                'icon' => null,
                'isconfiguration' => true,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $WorkflowID,
                'parent_id' => 4,
                'menuname' => 'Build Workflow',
                'description' => 'test',
                'menulink' => '/workflow',
                'icon' => null,
                'isconfiguration' => true,
                'menu_order' => 5,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $ProcurementManagementID,
                'parent_id' => null,
                'menuname' => 'Asset Management',
                'description' => 'test',
                'menulink' => '#',
                'icon' => 'RiHomeOfficeLine',
                'isconfiguration' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $AssetRequisitionsID,
                'parent_id' => 10,
                'menuname' => 'Asset Requisitions',
                'description' => 'test',
                'menulink' => '/asset_requisitions',
                'icon' => null,
                'isconfiguration' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $AssetsManagementID,
                'parent_id' => 10,
                'menuname' => 'Asset Groups',
                'description' => 'test',
                'menulink' => '/asset_groups',
                'icon' => null,
                'isconfiguration' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $AssetsItemsID,
                'parent_id' => 10,
                'menuname' => 'Asset Master',
                'description' => 'test',
                'menulink' => '/asset_items',
                'icon' => null,
                'isconfiguration' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $AssetCategoryReadingParametersID,
                'parent_id' => 10,
                'menuname' => 'My Assets',
                'description' => 'test',
                'menulink' => '/users_asset_items',
                'icon' => null,
                'isconfiguration' => false,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $ProcurementManagementID,
                'parent_id' => null,
                'menuname' => 'Procurement Management',
                'description' => 'test',
                'menulink' => '#',
                'icon' => 'VscServerProcess',
                'isconfiguration' => false,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $ProcurementInitiateID,
                'parent_id' => 15,
                'menuname' => 'Procurement Submissions',
                'description' => 'test',
                'menulink' => '/procurement_initiate',
                'icon' => null,
                'isconfiguration' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $ProcurementStaffID,
                'parent_id' => 15,
                'menuname' => 'Procurement Staff',
                'description' => 'test',
                'menulink' => '/staff',
                'icon' => null,
                'isconfiguration' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $SupplierQuotationID,
                'parent_id' => 15,
                'menuname' => 'Supplier Quotation',
                'description' => 'test',
                'menulink' => '/supplier_quotation',
                'icon' => null,
                'isconfiguration' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $SupplierQuotationID,
                'parent_id' => 4,
                'menuname' => 'Item Master',
                'description' => 'test',
                'menulink' => '/item_master',
                'icon' => null,
                'isconfiguration' => true,
                'menu_order' => 6,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'permission_id' => $workOrderPermissionID,
                'parent_id' => 10,
                'menuname' => 'Work Order',
                'description' => 'test',
                'menulink' => '/workorders',
                'icon' => null,
                'isconfiguration' => false,
                'menu_order' => 5,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ]
        ]);
    }
}
