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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
 
class ProcessTenantRegistrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $registrationDebugId;
    public $timeout = 120;
    public $tries = 3;
    public $backoff = [30, 60, 120];

    public function __construct($registrationDebugId)
    {
        $this->registrationDebugId = $registrationDebugId;
        $this->onQueue('tenant-registration');
    }

    public function handle()
    {
        $startTime = microtime(true);
        Log::info("ProcessTenantRegistrationJob started for ID: {$this->registrationDebugId}");
        
        // Database health check
        if (!$this->checkDatabaseHealth()) {
            $this->release(60);
            return;
        }
        
        $lockKey = 'tenant_reg_lock_' . $this->registrationDebugId;
        $reg = RegistrationDebug::find($this->registrationDebugId);
        
        if (!$reg) {
            Log::warning("Registration record not found: {$this->registrationDebugId}");
            return;
        }

        // Check if this registration has already been completed or has a tenant_id
        if (isset($reg->validated_user['tenant_id'])) {
            Log::info("Registration {$reg->id} already has tenant_id: {$reg->validated_user['tenant_id']}, checking completion status");
            
            // If status is not completed but has tenant_id, continue from where we left off
            if (!in_array($reg->status, ['completed', 'emails_sent', 'payment_processed'])) {
                Log::info("Registration {$reg->id} needs to continue from status: {$reg->status}");
                $this->handlePartialCompletion($reg, $startTime);
                return;
            } else {
                Log::info("Registration {$reg->id} is already completed with status: {$reg->status}");
                return;
            }
        }

        Log::info("Starting enterprise tenant registration chain for ID: {$reg->id}");

        try {
            $ownerUser = User::find($reg->owner_user_id);
            if (!$ownerUser) {
                throw new \Exception('Owner user not found');
            }

            Log::info("Owner user found: {$ownerUser->email} for registration ID: {$reg->id}");

            // Update metrics
            $this->updateProcessingMetrics('started');

            // Create jobs array with enterprise configuration - Fix serialization issue
            $jobs = [
                new CreateTenantDatabaseJob($reg->id),
                new CreateTenantUsersJob($reg->id),
                new SendTenantEmailsJob($reg->id),
                new ProcessTenantPaymentJob($reg->id),
            ];

            // Set queue for each job individually to avoid closure serialization issues
            foreach ($jobs as $job) {
                if (method_exists($job, 'onQueue')) {
                    if ($job instanceof SendTenantEmailsJob) {
                        $job->onQueue('emails');
                    } else {
                        $job->onQueue('tenant-registration');
                    }
                }
            }

            Log::info("Creating enterprise job chain with " . count($jobs) . " jobs for registration ID: {$reg->id}");

            // Create a simple job chain without closures that could cause serialization issues
            $chain = Bus::chain($jobs);
            
            // Set the catch handler using a more stable approach
            $registrationId = $reg->id;
            $lockKeyForCatch = $lockKey;
            $startTimeForCatch = $startTime;
            
            $chain->catch(function (\Throwable $e) use ($registrationId, $lockKeyForCatch, $startTimeForCatch) {
                Log::error("Enterprise job chain error for registration ID {$registrationId}: " . $e->getMessage());
                Log::error("Stack trace: " . $e->getTraceAsString());
                
                try {
                    $failedReg = RegistrationDebug::find($registrationId);
                    if ($failedReg) {
                        $failedReg->update([
                            'status' => 'failed', 
                            'error_message' => $e->getMessage(),
                            'processing_time' => microtime(true) - $startTimeForCatch
                        ]);
                    }
                    
                    // Clean up any cached data
                    Cache::forget("tenant_users_{$registrationId}");
                    
                    // Update failure metrics using static method call
                    ProcessTenantRegistrationJob::updateProcessingMetricsStatic('failed');
                    
                    // Release lock
                    try {
                        Cache::lock($lockKeyForCatch)->release();
                    } catch (\Exception $lockException) {
                        Log::warning("Failed to release lock for registration ID {$registrationId}: " . $lockException->getMessage());
                    }

                    // Send alert for enterprise failures
                    ProcessTenantRegistrationJob::sendEnterpriseAlertStatic($failedReg, $e);
                    
                } catch (\Exception $catchException) {
                    Log::error("Error in catch handler: " . $catchException->getMessage());
                }
            });

            $chain->onQueue('tenant-registration')->dispatch();

            Log::info("Enterprise job chain dispatched successfully for registration ID: {$reg->id}");

        } catch (\Exception $e) {
            Log::error("ProcessTenantRegistrationJob failed for ID: {$reg->id} - " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            $reg->update([
                'status' => 'failed', 
                'error_message' => $e->getMessage(),
                'processing_time' => microtime(true) - $startTime
            ]);
            
            $this->updateProcessingMetrics('failed');
            $this->sendEnterpriseAlert($reg, $e);
        }
        
        // Release lock
        try {
            Cache::lock($lockKey)->release();
        } catch (\Exception $lockException) {
            Log::warning("Failed to release lock at end for registration ID: {$this->registrationDebugId} - " . $lockException->getMessage());
        }
        
        $processingTime = microtime(true) - $startTime;
        Log::info("ProcessTenantRegistrationJob completed for ID: {$this->registrationDebugId} in {$processingTime}s");
    }

    private function checkDatabaseHealth(): bool
    {
        try {
            DB::select('SELECT 1');
            return true;
        } catch (\Exception $e) {
            Log::error("Database health check failed: " . $e->getMessage());
            return false;
        }
    }

    private function updateProcessingMetrics(string $status): void
    {
        self::updateProcessingMetricsStatic($status);
    }

    public static function updateProcessingMetricsStatic(string $status): void
    {
        try {
            $key = "tenant_registration_metrics_" . date('Y-m-d-H');
            $metrics = json_decode(Redis::get($key), true) ?: [];
            
            $metrics[$status] = ($metrics[$status] ?? 0) + 1;
            $metrics['last_update'] = now()->toISOString();
            
            Redis::setex($key, 3600 * 25, json_encode($metrics));
        } catch (\Exception $e) {
            Log::warning("Failed to update metrics: " . $e->getMessage());
        }
    }

    private function sendEnterpriseAlert($reg, \Throwable $e): void
    {
        self::sendEnterpriseAlertStatic($reg, $e);
    }

    public static function sendEnterpriseAlertStatic($reg, \Throwable $e): void
    {
        try {
            Log::critical("ENTERPRISE ALERT: Tenant registration failed", [
                'registration_id' => $reg ? $reg->id : 'unknown',
                'error' => $e->getMessage(),
                'owner_email' => $reg && $reg->ownerUser ? $reg->ownerUser->email : 'unknown',
                'package_type' => $reg ? $reg->package_type : 'unknown',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $alertException) {
            Log::error("Failed to send enterprise alert: " . $alertException->getMessage());
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::critical("ProcessTenantRegistrationJob failed permanently for ID: {$this->registrationDebugId} - " . $exception->getMessage());
        Log::error("Stack trace: " . $exception->getTraceAsString());
        
        $reg = RegistrationDebug::find($this->registrationDebugId);
        if ($reg) {
            $reg->update([
                'status' => 'failed', 
                'error_message' => "PERMANENT FAILURE: " . $exception->getMessage()
            ]);
        }
        
        $this->updateProcessingMetrics('permanent_failure');
    }

    private function handlePartialCompletion($reg, $startTime)
    {
        Log::info("Handling partial completion for registration ID: {$reg->id}, current status: {$reg->status}");
        
        try {
            $jobs = [];
            
            // Determine which jobs still need to run based on current status
            switch ($reg->status) {
                case 'database_created':
                    $jobs = [
                        new CreateTenantUsersJob($reg->id),
                        new SendTenantEmailsJob($reg->id),
                        new ProcessTenantPaymentJob($reg->id),
                    ];
                    break;
                    
                case 'users_created':
                    $jobs = [
                        new SendTenantEmailsJob($reg->id),
                        new ProcessTenantPaymentJob($reg->id),
                    ];
                    break;
                    
                case 'emails_sent':
                    $jobs = [
                        new ProcessTenantPaymentJob($reg->id),
                    ];
                    break;
                    
                default:
                    Log::info("No additional jobs needed for status: {$reg->status}");
                    return;
            }
            
            if (!empty($jobs)) {
                // Set queue for each job
                foreach ($jobs as $job) {
                    if ($job instanceof SendTenantEmailsJob) {
                        $job->onQueue('emails');
                    } else {
                        $job->onQueue('tenant-registration');
                    }
                }
                
                Log::info("Continuing registration with " . count($jobs) . " remaining jobs for ID: {$reg->id}");
                
                $registrationId = $reg->id;
                $startTimeForCatch = $startTime;
                
                Bus::chain($jobs)
                    ->catch(function (\Throwable $e) use ($registrationId, $startTimeForCatch) {
                        Log::error("Partial completion job chain error for registration ID {$registrationId}: " . $e->getMessage());
                        
                        $failedReg = RegistrationDebug::find($registrationId);
                        if ($failedReg) {
                            $failedReg->update([
                                'status' => 'failed', 
                                'error_message' => 'Retry failed: ' . $e->getMessage(),
                                'processing_time' => microtime(true) - $startTimeForCatch
                            ]);
                        }
                        
                        ProcessTenantRegistrationJob::updateProcessingMetricsStatic('retry_failed');
                    })
                    ->dispatch();
                    
                Log::info("Partial completion job chain dispatched for registration ID: {$reg->id}");
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to handle partial completion for ID: {$reg->id} - " . $e->getMessage());
            $reg->update([
                'status' => 'failed', 
                'error_message' => 'Retry setup failed: ' . $e->getMessage(),
                'processing_time' => microtime(true) - $startTime
            ]);
        }
    }
}
