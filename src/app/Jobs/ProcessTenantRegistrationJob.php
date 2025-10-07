<?php

namespace App\Jobs;

use App\Models\RegistrationDebug;
use App\Models\User;
use App\Jobs\CreateTenantDatabaseJob;
use App\Jobs\CreateTenantUsersJob;
use App\Jobs\SendTenantEmailsJob;
use App\Jobs\ProcessTenantPaymentJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;
 
class ProcessTenantRegistrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $registrationDebugId;
    public $timeout = 60; // Reduced timeout for orchestration only
    public $tries = 2;

    public function __construct($registrationDebugId)
    {
        $this->registrationDebugId = $registrationDebugId;
    }

    public function handle()
    {
        $lockKey = 'tenant_reg_lock_' . $this->registrationDebugId;
        $reg = RegistrationDebug::find($this->registrationDebugId);
        
        if (!$reg) {
            Log::warning("Registration record not found: {$this->registrationDebugId}");
            return;
        }

        Log::info("Starting optimized tenant registration chain for ID: {$reg->id}");

        try {
            $ownerUser = User::find($reg->owner_user_id);
            if (!$ownerUser) {
                throw new \Exception('Owner user not found');
            }

            // Create jobs array - now includes payment processing
            $jobs = [
                new CreateTenantDatabaseJob($reg->id),
                new CreateTenantUsersJob($reg->id),
                new SendTenantEmailsJob($reg->id),
                new ProcessTenantPaymentJob($reg->id), // New payment job
            ];

            Log::info("Creating job chain with " . count($jobs) . " jobs for registration ID: {$reg->id}");

            // Optimized job chain with proper error handling and debugging
            Bus::chain($jobs)
            ->onQueue('tenant-registration')
            ->catch(function (\Throwable $e) use ($reg, $lockKey) {
                Log::error("Job chain error for registration ID {$reg->id}: " . $e->getMessage());
                Log::error("Stack trace: " . $e->getTraceAsString());
                
                $reg->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                
                // Clean up any cached data
                Cache::forget("tenant_users_{$reg->id}");
                
                // Release lock in catch block
                try {
                    Cache::lock($lockKey)->release();
                } catch (\Exception $lockException) {
                    Log::warning("Failed to release lock for registration ID {$reg->id}: " . $lockException->getMessage());
                }
            })
            ->dispatch();

            Log::info("Job chain dispatched successfully for registration ID: {$reg->id}");

        } catch (\Exception $e) {
            $reg->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            Log::error("Tenant registration orchestration failed for ID: {$reg->id} - " . $e->getMessage());
        }
        
        // Release lock at the end of successful dispatch
        try {
            Cache::lock($lockKey)->release();
        } catch (\Exception $lockException) {
            Log::warning("Failed to release lock at end for registration ID: {$this->registrationDebugId} - " . $lockException->getMessage());
        }
    }

    public function failed(\Throwable $exception)
    {
        $reg = RegistrationDebug::find($this->registrationDebugId);
        if ($reg) {
            $reg->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
        }
        Log::error("Tenant registration job failed permanently for ID: {$this->registrationDebugId} - " . $exception->getMessage());
    }
}
