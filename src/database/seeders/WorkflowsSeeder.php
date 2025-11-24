<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WorkflowsSeeder extends Seeder
{ 
    protected $tenantId;

    public function __construct()
    {
        // Retrieve the selected user tenant id from the service container when create a tenant
        if (app()->bound('selectedTenantId')) {
            $this->tenantId = app()->make('selectedTenantId');
        }
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currentTime = Carbon::now();
        $tenant_id = $this->tenantId ?? User::find(1)?->tenant_id;
        
        $workflowsData = [
            [
                'workflow_request_type_id' => 1,
                'workflow_name' => 'Asset Requisition Workflow',
                'workflow_description' => 'Workflow for asset requisition requests.',
            ],
            [
                'workflow_request_type_id' => 2,
                'workflow_name' => 'Supplier Registration Workflow',
                'workflow_description' => 'Workflow for supplier registration requests.',
            ],
            [
                'workflow_request_type_id' => 3,
                'workflow_name' => 'Procurement Registration Workflow',
                'workflow_description' => 'Workflow for procurement registration requests.',
            ],
            [
                'workflow_request_type_id' => 4,
                'workflow_name' => 'Work Order Requisition Workflow',
                'workflow_description' => 'Workflow for work order requisition requests.',
            ],
            [
                'workflow_request_type_id' => 5,
                'workflow_name' => 'Asset Booking Requisition Workflow',
                'workflow_description' => 'Workflow for asset booking requisition requests.',
            ],
            [
                'workflow_request_type_id' => 6,
                'workflow_name' => 'Customer Registration Workflow',
                'workflow_description' => 'Workflow for customer registration requests.',
            ],
            [
                'workflow_request_type_id' => 7,
                'workflow_name' => 'Purchase Order Submission Workflow',
                'workflow_description' => 'Workflow for purchase order submission requests.',
            ],
        ];

        foreach ($workflowsData as $workflowData) {
            $existing = DB::table('workflows')
                ->where('tenant_id', $tenantId)
                ->where('workflow_request_type_id', $workflowData['workflow_request_type_id'])
                ->first();

            if ($existing) {
                // Update existing record
                DB::table('workflows')
                    ->where('id', $existing->id)
                    ->update([
                        'workflow_name' => $workflowData['workflow_name'],
                        'workflow_description' => $workflowData['workflow_description'],
                        'workflow_status' => true,
                        'deleted_at' => null,
                        'updated_at' => $currentTime
                    ]);
            } else {
                // Insert new record with sequential ID
                $maxId = DB::table('workflows')->max('id') ?? 0;
                DB::table('workflows')->insert([
                    'id' => $maxId + 1,
                    'workflow_request_type_id' => $workflowData['workflow_request_type_id'],
                    'workflow_name' => $workflowData['workflow_name'],
                    'workflow_description' => $workflowData['workflow_description'],
                    'workflow_status' => true,
                    'is_published' => false,
                    'deleted_at' => null,
                    'tenant_id' => $tenantId,
                    'created_at' => $currentTime,
                    'updated_at' => $currentTime
                ]);
            }
        }

        // Reset the sequence to match the current max ID
        $maxId = DB::table('workflows')->max('id');
        DB::statement("SELECT setval('workflows_id_seq', $maxId)");
    }
}
