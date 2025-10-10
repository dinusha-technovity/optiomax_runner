<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TenantPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currentTime = Carbon::now();
        DB::table('permissions')->truncate();
        $permission = [
            // 1
            [
                'name' => 'User Management',
                'description' => 'test',
                'parent_id' => null,
                'menulink' => '#',  
                'icon' => 'MdManageAccounts',
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 2
            [
                'name' => 'User Roles',
                'description' => 'test',
                'parent_id' => 1,
                'menulink' => '/Roles',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 3
            [
                'name' => 'Create Role',
                'description' => 'test',
                'parent_id' => 2,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 4
            [
                'name' => 'Edit Role',
                'description' => 'test',
                'parent_id' => 2,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 5
            [
                'name' => 'Delete Role',
                'description' => 'test',
                'parent_id' => 2,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 6
            [
                'name' => 'Give Permissions to Role',
                'description' => 'test',
                'parent_id' => 2,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 7
            [
                'name' => 'User Accounts',
                'description' => 'test',
                'parent_id' => 1,
                'menulink' => '/users',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 8
            [
                'name' => 'Create User',
                'description' => 'test',
                'parent_id' => 7,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 9
            [
                'name' => 'Edit User',
                'description' => 'test',
                'parent_id' => 7,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 10
            [
                'name' => 'Delete User',
                'description' => 'test',
                'parent_id' => 7,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 11
            [
                'name' => 'User Status Change',
                'description' => 'test',
                'parent_id' => 7,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 12
            [
                'name' => 'User Password Reset',
                'description' => 'test',
                'parent_id' => 7,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 5,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 13
            [
                'name' => 'Master Configuration',
                'description' => 'test',
                'parent_id' => null,
                'menulink' => '#',
                'icon' => 'Settings2',
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 14
            [
                'name' => 'Manage Suppliers',
                'description' => 'test',
                'parent_id' => 13,
                'menulink' => '/supplier',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 15
            [
                'name' => 'Register New Supplier',
                'description' => 'test',
                'parent_id' => 14,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 16
            [
                'name' => 'Edit Supplier Details',
                'description' => 'test',
                'parent_id' => 14,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 17
            [
                'name' => 'Delete Supplier',
                'description' => 'test',
                'parent_id' => 14,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 18
            [
                'name' => 'Supplier Invitation Send',
                'description' => 'test',
                'parent_id' => 14,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 19
            [
                'name' => 'Asset Categories',
                'description' => 'test',
                'parent_id' => 126,
                'menulink' => '/asset_categories',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 20
            [
                'name' => 'Create Asset Categories',
                'description' => 'test',
                'parent_id' => 19,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 21
            [
                'name' => 'Edit Asset Categories',
                'description' => 'test',
                'parent_id' => 19,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 22
            [
                'name' => 'Delete Asset Categories',
                'description' => 'test',
                'parent_id' => 19,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 23
            [
                'name' => 'Update Reading Parameters',
                'description' => 'test',
                'parent_id' => 19,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 5,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 24
            [
                'name' => 'Asset Sub Categories',
                'description' => 'test',
                'parent_id' => 126,
                'menulink' => '/sub_asset_categories',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 25
            [
                'name' => 'Create Asset Sub Categories',
                'description' => 'test',
                'parent_id' => 24,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 26
            [
                'name' => 'Edit Asset Sub Categories',
                'description' => 'test',
                'parent_id' => 24,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 27
            [
                'name' => 'Delete Asset Sub Categories',
                'description' => 'test',
                'parent_id' => 24,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 28
            [
                'name' => 'Update Sub Categories Reading Parameters',
                'description' => 'test',
                'parent_id' => 24,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 29
            [
                'name' => 'Build Organization',
                'description' => 'test',
                'parent_id' => 13,
                'menulink' => '/organization',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 30
            [
                'name' => 'Create Organization Node',
                'description' => 'test',
                'parent_id' => 29,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 31
            [
                'name' => 'Edit Organization Node',
                'description' => 'test',
                'parent_id' => 30,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 32
            [
                'name' => 'Delete Organization Node',
                'description' => 'test',
                'parent_id' => 30,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 33
            [
                'name' => 'Build Workflow',
                'description' => 'test',
                'parent_id' => 13,
                'menulink' => '/workflow',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 5,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 34
            [
                'name' => 'Create Workflow',
                'description' => 'test',
                'parent_id' => 33,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 35
            [
                'name' => 'Edit Workflow',
                'description' => 'test',
                'parent_id' => 33,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ], 
            // 36
            [
                'name' => 'Delete Workflow',
                'description' => 'test',
                'parent_id' => 33,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ], 
            // 37
            [
                'name' => 'Config Workflow',
                'description' => 'test',
                'parent_id' => 33,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ], 
            // 38
            [
                'name' => 'Add Workflow Node',
                'description' => 'test',
                'parent_id' => 37,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ], 
            // 39
            [
                'name' => 'Edit Workflow Node',
                'description' => 'test',
                'parent_id' => 37,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ], 
            // 40
            [
                'name' => 'Delete Workflow Node',
                'description' => 'test',
                'parent_id' => 37,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ], 
            // 41
            [
                'name' => 'Publish Workflow',
                'description' => 'test',
                'parent_id' => 37,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ], 
            // 42
            [
                'name' => 'Item Master',
                'description' => 'test',
                'parent_id' => 13,
                'menulink' => '/item_master',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 6,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 43
            [
                'name' => 'Add New Item',
                'description' => 'test',
                'parent_id' => 42,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 44
            [
                'name' => 'Edit Item Details',
                'description' => 'test',
                'parent_id' => 42,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 45
            [
                'name' => 'Delete Item',
                'description' => 'test',
                'parent_id' => 42,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 46
            [
                'name' => 'Procurement Staff',
                'description' => 'test',
                'parent_id' => 13,
                'menulink' => '/staff',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 7,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 47
            [
                'name' => 'Create Procurement Staff',
                'description' => 'test',
                'parent_id' => 46,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 48
            [
                'name' => 'Edit Procurement Staff',
                'description' => 'test',
                'parent_id' => 46,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 49
            [
                'name' => 'Delete Procurement Staff',
                'description' => 'test',
                'parent_id' => 46,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 50
            [
                'name' => 'Asset Management',
                'description' => 'test',
                'parent_id' => null,
                'menulink' => '#',
                'icon' => 'TbSettingsDollar',
                'isconfiguration' => false,
                'ismenu_list' => true,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 51
            [
                'name' => 'Asset Requisitions',
                'description' => 'test',
                'parent_id' => 50,
                'menulink' => '/asset_requisitions',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => true,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 52
            [
                'name' => 'Create Asset Requisitions',
                'description' => 'test',
                'parent_id' => 51,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 53
            [
                'name' => 'Asset Groups',
                'description' => 'test',
                'parent_id' => 126,
                'menulink' => '/asset_groups',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 54
            [
                'name' => 'Create Asset Group',
                'description' => 'test',
                'parent_id' => 53,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 55
            [
                'name' => 'View Asset Group Details',
                'description' => 'test',
                'parent_id' => 53,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 56
            [
                'name' => 'Edit Asset Group Details',
                'description' => 'test',
                'parent_id' => 53,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 57
            [
                'name' => 'Delete Asset Group',
                'description' => 'test',
                'parent_id' => 53,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 58
            [
                'name' => 'Asset Group Description Tags',
                'description' => 'test',
                'parent_id' => 53,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 5,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 59
            [
                'name' => 'Asset Group Reading Parameters',
                'description' => 'test',
                'parent_id' => 53,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 6,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 60
            [
                'name' => 'Asset Group Maintainers Schedule',
                'description' => 'test',
                'parent_id' => 53,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 6,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 61
            [
                'name' => 'Asset Group Manufacturer Recommendations Maintainers Schedule',
                'description' => 'test',
                'parent_id' => 60,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 62
            [
                'name' => 'Add Asset Group Manufacturer Recommendations Schedule',
                'description' => 'test',
                'parent_id' => 61,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 63
            [
                'name' => 'Edit Asset Group Manufacturer Recommendations Schedule',
                'description' => 'test',
                'parent_id' => 61,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 64
            [
                'name' => 'Delete Asset Group Manufacturer Recommendations Schedule',
                'description' => 'test',
                'parent_id' => 61,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 65
            [
                'name' => 'Asset Group Usage Base Maintainers Schedule',
                'description' => 'test',
                'parent_id' => 60,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 66
            [
                'name' => 'Add Asset Group Usage Base Schedule',
                'description' => 'test',
                'parent_id' => 65,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 67
            [
                'name' => 'Edit Asset Group Usage Base Schedule',
                'description' => 'test',
                'parent_id' => 65,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 68
            [
                'name' => 'Delete Asset Group Usage Based Schedule',
                'description' => 'test',
                'parent_id' => 65,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 69
            [
                'name' => 'Asset Group Critically Based Maintainers Schedule',
                'description' => 'test',
                'parent_id' => 60,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 9,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 70
            [
                'name' => 'Add Asset Group Critically Based Schedule',
                'description' => 'test',
                'parent_id' => 69,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 71
            [
                'name' => 'Edit Asset Group Critically Based Schedule',
                'description' => 'test',
                'parent_id' => 69,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 72
            [
                'name' => 'Delete Asset Group Critically Based Schedule',
                'description' => 'test',
                'parent_id' => 69,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 73
            [
                'name' => 'Asset Group Maintenance Task Schedule',
                'description' => 'test',
                'parent_id' => 60,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 10,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 74
            [
                'name' => 'Add Asset Group Maintenance Task Schedule',
                'description' => 'test',
                'parent_id' => 73,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 75
            [
                'name' => 'Edit Asset Group Maintenance Task Schedule',
                'description' => 'test',
                'parent_id' => 73,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 76
            [
                'name' => 'Delete Asset Group Maintenance Task Schedule',
                'description' => 'test',
                'parent_id' => 73,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 77
            [
                'name' => 'Asset Master',
                'description' => 'test',
                'parent_id' => 126,
                'menulink' => '/asset_master',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 78
            [
                'name' => 'Register Asset Master',
                'description' => 'test',
                'parent_id' => 77,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 79
            [
                'name' => 'Edit Asset Master',
                'description' => 'test',
                'parent_id' => 77,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 80
            [
                'name' => 'Delete Asset Master',
                'description' => 'test',
                'parent_id' => 77,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 81
            [
                'name' => 'Asset Master Maintainers Schedule',
                'description' => 'test',
                'parent_id' => 77,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 6,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 82
            [
                'name' => 'Asset Master Manufacturer Recommendations Maintainers Schedule',
                'description' => 'test',
                'parent_id' => 81,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 83
            [
                'name' => 'Add Asset Master Manufacturer Recommendations Schedule',
                'description' => 'test',
                'parent_id' => 82,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 84
            [
                'name' => 'Edit Asset Master Manufacturer Recommendations Schedule',
                'description' => 'test',
                'parent_id' => 82,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 85
            [
                'name' => 'Delete Asset Master Manufacturer Recommendations Schedule',
                'description' => 'test',
                'parent_id' => 82,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 86
            [
                'name' => 'Asset Master Usage Base Maintainers Schedule',
                'description' => 'test',
                'parent_id' => 77,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 87
            [
                'name' => 'Add Asset Master Usage Base Schedule',
                'description' => 'test',
                'parent_id' => 86,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 88
            [
                'name' => 'Edit Asset Master Usage Base Schedule',
                'description' => 'test',
                'parent_id' => 86,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 89
            [
                'name' => 'Delete Asset Master Usage Based Schedule',
                'description' => 'test',
                'parent_id' => 86,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 90
            [
                'name' => 'Asset Master Critically Based Maintainers Schedule',
                'description' => 'test',
                'parent_id' => 77,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 9,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 91
            [
                'name' => 'Add Asset Master Critically Based Schedule',
                'description' => 'test',
                'parent_id' => 90,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 92
            [
                'name' => 'Edit Asset Master Critically Based Schedule',
                'description' => 'test',
                'parent_id' => 90,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 93
            [
                'name' => 'Delete Asset Master Critically Based Schedule',
                'description' => 'test',
                'parent_id' => 90,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 94
            [
                'name' => 'Asset Master Maintenance Task Schedule',
                'description' => 'test',
                'parent_id' => 77,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 10,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 95
            [
                'name' => 'Add Asset Master Maintenance Task Schedule',
                'description' => 'test',
                'parent_id' => 94,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 96
            [
                'name' => 'Edit Asset Master Maintenance Task Schedule',
                'description' => 'test',
                'parent_id' => 94,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 97
            [
                'name' => 'Delete Asset Master Maintenance Task Schedule',
                'description' => 'test',
                'parent_id' => 94,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 98
            [
                'name' => 'My Assets',
                'description' => 'test',
                'parent_id' => 50,
                'menulink' => '/users_asset_items',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => true,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 99
            [
                'name' => 'View My Assets Details',
                'description' => 'test',
                'parent_id' => 98,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 100
            [
                'name' => 'Add My Assets Readings',
                'description' => 'test',
                'parent_id' => 98,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 101
            [
                'name' => 'Work Orders',
                'description' => 'test',
                'parent_id' => 50,
                'menulink' => '/workorders',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => true,
                'menu_order' => 5,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 102
            [
                'name' => 'Add New Work Order',
                'description' => 'test',
                'parent_id' => 101,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 103
            [
                'name' => 'Edit Work Order',
                'description' => 'test',
                'parent_id' => 101,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 104
            [
                'name' => 'Delete Work Order',
                'description' => 'test',
                'parent_id' => 101,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 105
            [
                'name' => 'Update Work Order Status',
                'description' => 'test',
                'parent_id' => 101,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 106
            [
                'name' => 'Procurement Management',
                'description' => 'test',
                'parent_id' => null,
                'menulink' => '#',
                'icon' => 'FileSpreadsheet',
                'isconfiguration' => false,
                'ismenu_list' => true,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 107
            [
                'name' => 'Procurement Submissions',
                'description' => 'test',
                'parent_id' => 106,
                'menulink' => '/procurement_initiate',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => true,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 108
            [
                'name' => 'Procurement Submission',
                'description' => 'test',
                'parent_id' => 107,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 109
            [
                'name' => 'View Received Quotation',
                'description' => 'test',
                'parent_id' => 107,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 110
            [
                'name' => 'Proceed Procurement',
                'description' => 'test',
                'parent_id' => 107,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 111
            [
                'name' => 'Supplier Quotations',
                'description' => 'test',
                'parent_id' => 106,
                'menulink' => '/supplier_quotation',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => true,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 112
            [
                'name' => 'Add Supplier Quotation',
                'description' => 'test',
                'parent_id' => 111,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 113
            [
                'name' => 'View Added Supplier Quotation',
                'description' => 'test',
                'parent_id' => 111,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 114
            [
                'name' => 'View Supplier Quotation Details',
                'description' => 'test',
                'parent_id' => 113,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 115
            [
                'name' => 'Edit Supplier Quotation',
                'description' => 'test',
                'parent_id' => 113,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 116
            [
                'name' => 'Delete Supplier Quotation',
                'description' => 'test',
                'parent_id' => 113,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 117
            [
                'name' => 'Supplier Quotation Adding Complete',
                'description' => 'test',
                'parent_id' => 111,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 118
            [
                'name' => 'Edit Selected Item Qty',
                'description' => 'test',
                'parent_id' => 108,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 119
            [
                'name' => 'Edit Selected Item Budget',
                'description' => 'test',
                'parent_id' => 108,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 120
            [
                'name' => 'Incidents',
                'description' => 'test',
                'parent_id' => 50,
                'menulink' => '/incident_reports',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => true,
                'menu_order' => 6,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 121
            [
                'name' => 'New Incident',
                'description' => 'test',
                'parent_id' => 120,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 122
            [
                'name' => 'View Incident',
                'description' => 'test',
                'parent_id' => 120,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 123
            [
                'name' => 'Edit Incident',
                'description' => 'test',
                'parent_id' => 120,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 124
            [
                'name' => 'Delete Incident',
                'description' => 'test',
                'parent_id' => 120,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 125
            [
                'name' => 'View Asset Categories',
                'description' => 'test',
                'parent_id' => 19,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 126
            [
                'name' => 'Asset',
                'description' => 'test',
                'parent_id' => null,
                'menulink' => '#',
                'icon' => 'Package2',
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
             // 127
            [
                'name' => 'Maintenance',
                'description' => 'test',
                'parent_id' => null,
                'menulink' => '#',
                'icon' => 'Wrench',
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 128
            [
                'name' => 'Maintenance Team',
                'description' => 'test',
                'parent_id' => 127,
                'menulink' => '/maintenance_team',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 129
            [
                'name' => 'Register Maintenance Team',
                'description' => 'test',
                'parent_id' => 128,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 130
            [
                'name' => 'Edit Maintenance Team',
                'description' => 'test',
                'parent_id' => 128,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 131
            [
                'name' => 'Delete Maintenance Team',
                'description' => 'test',
                'parent_id' => 128,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 132
            [
                'name' => 'Purchasing',
                'description' => 'test',
                'parent_id' => null,
                'menulink' => '#',
                'icon' => 'FiShoppingCart',
                'isconfiguration' => false,
                'ismenu_list' => true,
                'menu_order' => 5,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 133
            [
                'name' => 'Goods Received Note',
                'description' => 'test',
                'parent_id' => 132,
                'menulink' => '/goods_received_note',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => true,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 134
            [
                'name' => 'Create Goods Received Note',
                'description' => 'test',
                'parent_id' => 133,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 135
            [
                'name' => 'View Goods Received Note',
                'description' => 'test',
                'parent_id' => 133,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 136
            [
                'name' => 'Cancel Goods Received Note',
                'description' => 'test',
                'parent_id' => 133,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 137
            [
                'name' => 'Export Goods Received Note',
                'description' => 'test',
                'parent_id' => 133,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 138
            [
                'name' => 'System Configuration',
                'description' => 'test',
                'parent_id' => null,
                'menulink' => '/system_configurations',
                'icon' => 'GrDocumentConfig',
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 6,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 139
            [
                'name' => 'Asset Availability',
                'description' => 'test',
                'parent_id' => 50,
                'menulink' => '/asset_availability',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => true,
                'menu_order' => 7,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 140
            [
                'name' => 'Schedule Assets',
                'description' => 'test',
                'parent_id' => 139,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 141
            [
                'name' => 'Availability Schedule',
                'description' => 'test',
                'parent_id' => 140,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 142
            [
                'name' => 'Create Availability Schedule',
                'description' => 'test',
                'parent_id' => 141,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 143
            [
                'name' => 'Edit Availability Schedule',
                'description' => 'test',
                'parent_id' => 141,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 144
            [
                'name' => 'Delete Availability Schedule',
                'description' => 'test',
                'parent_id' => 141,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 145
            [
                'name' => 'View Availability Schedule',
                'description' => 'test',
                'parent_id' => 141,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 146
            [
                'name' => 'Publish/Unpublish Availability Schedule',
                'description' => 'test',
                'parent_id' => 141,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 5,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 147
            [
                'name' => 'Blockout Schedule',
                'description' => 'test',
                'parent_id' => 140,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 148
            [
                'name' => 'Create Blockout Schedule',
                'description' => 'test',
                'parent_id' => 147,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 149
            [
                'name' => 'Edit Blockout Schedule',
                'description' => 'test',
                'parent_id' => 147,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 150
            [
                'name' => 'Delete Blockout Schedule',
                'description' => 'test',
                'parent_id' => 147,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 151
            [
                'name' => 'View Blockout Schedule',
                'description' => 'test',
                'parent_id' => 147,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 152
            [
                'name' => 'Publish/Unpublish Blockout Schedule',
                'description' => 'test',
                'parent_id' => 147,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 5,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 153
            [
                'name' => 'View Assets Details',
                'description' => 'test',
                'parent_id' => 139,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 154
            [
                'name' => 'My Bookings',
                'description' => 'test',
                'parent_id' => 50,
                'menulink' => '/my_bookings',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => true,
                'menu_order' => 8,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 155
            [
                'name' => 'Assets Availability and Booking',
                'description' => 'test',
                'parent_id' => null,
                'menulink' => '#',
                'icon' => 'CalendarCheck',
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 156
            [
                'name' => 'Assets Availability terms type',
                'description' => 'test',
                'parent_id' => 155,
                'menulink' => '/assets_availability_terms_type',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 157
            [
                'name' => 'Create Terms Type',
                'description' => 'test',
                'parent_id' => 156,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 1,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 158
            [
                'name' => 'Delete Terms Type',
                'description' => 'test',
                'parent_id' => 156,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 159
            [
                'name' => 'View Terms Type',
                'description' => 'test',
                'parent_id' => 156,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 3,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 160
            [
                'name' => 'Edit Terms Type',
                'description' => 'test',
                'parent_id' => 156,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 4,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 161
            [
                'name' => 'Manage Customers',
                'description' => 'test',
                'parent_id' => 155,
                'menulink' => '/customers',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => true,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],

            // 162
            [
                'name' => 'Create Customer',
                'description' => 'test',
                'parent_id' => 161,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],

             // 163
            [
                'name' => 'Edit Customer',
                'description' => 'test',
                'parent_id' => 161,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],

             // 164
            [
                'name' => 'Delete Customer',
                'description' => 'test',
                'parent_id' => 161,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],

              // 165
            [
                'name' => 'View Customer',
                'description' => 'test',
                'parent_id' => 161,
                'menulink' => '#',
                'icon' => null,
                'isconfiguration' => true,
                'ismenu_list' => false,
                'menu_order' => 2,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // 166
            [
                'name' => 'External Asset Reservation',
                'description' => 'test',
                'parent_id' => 50,
                'menulink' => '/external_asset_reservation',
                'icon' => null,
                'isconfiguration' => false,
                'ismenu_list' => true,
                'menu_order' => 9,
                'isactive' => true,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
        ];

        // Seed multiple permission
        foreach ($permission as $Permission) {
            Permission::create($Permission);
        }
    }
} 