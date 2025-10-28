<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SidebarMenusDataSeeder extends Seeder
{
    public function run(): void
    { 
        $currentTime = Carbon::now();

        // Truncate the table before seeding
        DB::table('portal_sidebar_menu_list')->truncate();

        // Insert all menu data in a single call, with timestamps
        DB::table('portal_sidebar_menu_list')->insert([
            [
                'label' => 'Home',
                'key' => 'home',
                'icon' => 'HomeOutlined',
                'href' => '/home',
                'level' => 1,
                'parent_id' => null,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Optiomesh Docs',
                'key' => 'optiomesh-docs',
                'icon' => 'AppstoreOutlined',
                'href' => '/optiomesh_docs',
                'level' => 1,
                'parent_id' => null,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Payment',
                'key' => 'payment',
                'icon' => 'PayCircleOutlined',
                'href' => '/payment',
                'level' => 1,
                'parent_id' => null,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Activity',
                'key' => 'activity',
                'icon' => 'AppstoreOutlined',
                'href' => '/dashboard/activity',
                'level' => 1,
                'parent_id' => null,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Progress',
                'key' => 'progress',
                'icon' => 'AreaChartOutlined',
                'href' => '/progress',
                'level' => 1,
                'parent_id' => null,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Setting',
                'key' => 'setting',
                'icon' => 'SettingOutlined',
                'href' => '/setting',
                'level' => 1,
                'parent_id' => null,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Task',
                'key' => 'task',
                'icon' => 'BarsOutlined',
                'href' => null,
                'level' => 1,
                'parent_id' => null,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // Level 2 (children of 'task')
            [
                'label' => 'Task 1',
                'key' => 'task-1',
                'icon' => null,
                'href' => '/dashboard/workflow',
                'level' => 2,
                'parent_id' => 6,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Task 2',
                'key' => 'task-2',
                'icon' => null,
                'href' => '/task-2',
                'level' => 2,
                'parent_id' => 6,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Task 3',
                'key' => 'task-3',
                'icon' => null,
                'href' => '/task-3',
                'level' => 2,
                'parent_id' => 6,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Task 4',
                'key' => 'subtask1',
                'icon' => null,
                'href' => null,
                'level' => 2,
                'parent_id' => 6,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Task 5',
                'key' => 'task-5',
                'icon' => null,
                'href' => '/task-5',
                'level' => 2,
                'parent_id' => 6,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Task 6',
                'key' => 'task-6',
                'icon' => null,
                'href' => '/task-6',
                'level' => 2,
                'parent_id' => 6,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Task 7',
                'key' => 'subtask2',
                'icon' => null,
                'href' => null,
                'level' => 2,
                'parent_id' => 6,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // Level 3 (children of 'subtask1' - id 10)
            [
                'label' => 'Sub Task 1',
                'key' => 'sub-task1-1',
                'icon' => null,
                'href' => '/sub-task1-1',
                'level' => 3,
                'parent_id' => 10,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Sub Task 2',
                'key' => 'sub-task1-2',
                'icon' => null,
                'href' => '/sub-task1-2',
                'level' => 3,
                'parent_id' => 10,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Sub Task 3',
                'key' => 'sub-task1-3',
                'icon' => null,
                'href' => '/sub-task1-3',
                'level' => 3,
                'parent_id' => 10,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Sub Task 4',
                'key' => 'sub-task1-4',
                'icon' => null,
                'href' => '/sub-task1-4',
                'level' => 3,
                'parent_id' => 10,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            // Level 3 (children of 'subtask2' - id 13)
            [
                'label' => 'Sub Task 1',
                'key' => 'sub-task2-1',
                'icon' => null,
                'href' => '/sub-task2-1',
                'level' => 3,
                'parent_id' => 13,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Sub Task 2',
                'key' => 'sub-task2-2',
                'icon' => null,
                'href' => '/sub-task2-2',
                'level' => 3,
                'parent_id' => 13,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Sub Task 3',
                'key' => 'sub-task2-3',
                'icon' => null,
                'href' => '/sub-task2-3',
                'level' => 3,
                'parent_id' => 13,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'label' => 'Sub Task 4',
                'key' => 'sub-task2-4',
                'icon' => null,
                'href' => '/sub-task2-4',
                'level' => 3,
                'parent_id' => 13,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
        ]);
    }
}