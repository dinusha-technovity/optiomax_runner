<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TenantRequestTypesSeeder extends Seeder
{
    public function run(): void
    {
        $currentTime = Carbon::now();
        DB::table('workflow_request_types')->truncate();
        $requestTypes = [
            [
                'request_type' => 'Asset Requisition',
                'description'  => 'Request for new assets required by the organization.',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'request_type' => 'Supplier Registration',
                'description'  => 'Register a new supplier for business transactions.',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'request_type' => 'Procurement Registration',
                'description'  => 'Register procurement activities for tracking and approval.',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'request_type' => 'Work Order Requisition',
                'description'  => 'Request for creation of work orders for tasks or projects.',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'request_type' => 'Asset Booking Requisition',
                'description'  => 'Book assets for temporary use or reservation.',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'request_type' => 'Customer Registration',
                'description'  => 'Register a new customer in the system.',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
        ];

        DB::table('workflow_request_types')->insert($requestTypes);
    }
} 