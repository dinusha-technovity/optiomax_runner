<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RegistrationDebug;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;

class DebugTenantRegistration extends Command
{
    protected $signature = 'debug:tenant-registration {--id= : Specific registration ID to debug}';
    protected $description = 'Debug tenant registration queue and status';

    public function handle()
    {
        $registrationId = $this->option('id');
        
        $this->info('Tenant Registration Debug Information');
        $this->info('======================================');
        
        // Queue status
        $this->info('Queue Status:');
        $tenantRegQueueSize = Redis::llen('queues:tenant-registration');
        $defaultQueueSize = Redis::llen('queues:default');
        $emailQueueSize = Redis::llen('queues:emails');
        $failedJobs = Redis::llen('queues:failed');
        
        $this->info("  - Tenant Registration Queue: {$tenantRegQueueSize} jobs");
        $this->info("  - Default Queue: {$defaultQueueSize} jobs");
        $this->info("  - Email Queue: {$emailQueueSize} jobs");
        $this->info("  - Failed Jobs: {$failedJobs} jobs");
        
        // Registration status summary
        $this->info("\nRegistration Status Summary:");
        $pending = RegistrationDebug::where('status', 'pending')->count();
        $processing = RegistrationDebug::where('status', 'processing')->count();
        $completed = RegistrationDebug::where('status', 'completed')->count();
        $failed = RegistrationDebug::where('status', 'failed')->count();
        
        $this->info("  - Pending: {$pending}");
        $this->info("  - Processing: {$processing}");
        $this->info("  - Completed: {$completed}");
        $this->info("  - Failed: {$failed}");
        
        // Specific registration details
        if ($registrationId) {
            $this->info("\nRegistration ID {$registrationId} Details:");
            $reg = RegistrationDebug::find($registrationId);
            if ($reg) {
                $this->info("  - Status: {$reg->status}");
                $this->info("  - Created: {$reg->created_at}");
                $this->info("  - Updated: {$reg->updated_at}");
                if ($reg->error_message) {
                    $this->error("  - Error: {$reg->error_message}");
                }
            } else {
                $this->error("Registration ID {$registrationId} not found");
            }
        }
        
        // Show processing registrations
        $processingRegs = RegistrationDebug::where('status', 'processing')->get();
        if ($processingRegs->count() > 0) {
            $this->info("\nCurrently Processing Registrations:");
            foreach ($processingRegs as $reg) {
                $timeAgo = $reg->updated_at->diffForHumans();
                $this->info("  - ID {$reg->id}: Started {$timeAgo}");
            }
        }
        
        // Show failed registrations
        $failedRegs = RegistrationDebug::where('status', 'failed')->orderBy('updated_at', 'desc')->limit(5)->get();
        if ($failedRegs->count() > 0) {
            $this->info("\nRecent Failed Registrations:");
            foreach ($failedRegs as $reg) {
                $timeAgo = $reg->updated_at->diffForHumans();
                $this->error("  - ID {$reg->id}: Failed {$timeAgo} - {$reg->error_message}");
            }
        }
    }
}
