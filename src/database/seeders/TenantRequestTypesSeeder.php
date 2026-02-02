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
          $requestTypes = [
            [
                'id' => 1,
                'request_type' => 'Asset Requisition',
                'description'  => 'Request for new assets required by the organization.',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'id' => 2,
                'request_type' => 'Supplier Registration',
                'description'  => 'Register a new supplier for business transactions.',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'id' => 3,
                'request_type' => 'Procurement Registration',
                'description'  => 'Register procurement activities for tracking and approval.',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'id' => 4,
                'request_type' => 'Work Order Requisition',
                'description'  => 'Request for creation of work orders for tasks or projects.',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'id' => 5,
                'request_type' => 'Asset Booking Requisition',
                'description'  => 'Book assets for temporary use or reservation.',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'id' => 6,
                'request_type' => 'Customer Registration',
                'description'  => 'Register a new customer in the system.',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'id' => 7,
                'request_type' => 'Purchase Order Submission',
                'description'  => 'Submit a new purchase order for approval.',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'id' => 8,
                'request_type' => 'Asset Request from Owner Requisition',
                'description'  => 'Request assets from the asset owner for specific needs.',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
            [
                'id' => 9,
                'request_type' => 'Direct Asset Transfer Requests',
                'description'  => 'Manage direct asset transfer requests.',
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ],
        ];
        DB::table('workflow_request_types')->upsert($requestTypes, ['id'], ['request_type'], ['description', 'updated_at']);
    }
} 