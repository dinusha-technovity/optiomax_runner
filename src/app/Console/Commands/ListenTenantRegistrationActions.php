<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RegistrationDebug;
use App\Jobs\ProcessTenantRegistrationJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
 
class ListenTenantRegistrationActions extends Command
{
    protected $signature = 'listen:tenant-registration-actions {--batch=10 : Number of registrations to process per batch} {--monitor : Enable monitoring mode} {--daemon : Run as daemon}';
    protected $description = 'Process pending tenant registrations with optimized batch processing and monitoring.';
    
    private $shouldQuit = false;

    public function handle()
    {
        $batchSize = (int) $this->option('batch');
        $monitorMode = $this->option('monitor');
        $daemonMode = $this->option('daemon');
        
        if ($monitorMode) {
            $this->displayEnterpriseMonitoring();
            return;
        }
        
        // Health check before starting
        if (!$this->performHealthCheck()) {
            $this->error("Health check failed. Exiting...");
            return 1;
        }
        
        // Set up signal handlers for graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGUSR1, [$this, 'handleSignal']);
        }
        
        $this->info("Processing Tenant Registrations (optimized batch size: {$batchSize})...");
        
        do {
            // Check if there are any pending registrations first
            $pendingCount = RegistrationDebug::where('status', 'pending')->count();
            
            if ($pendingCount === 0) {
                if ($daemonMode) {
                    $this->info("No pending registrations. Sleeping for 30 seconds...");
                    sleep(30); // Sleep longer when no work to do
                    continue;
                } else {
                    $this->info("No pending registrations found. Exiting.");
                    break;
                }
            }
            
            $this->info("Found {$pendingCount} pending registrations. Processing...");
            $processedCount = 0;
            $failedCount = 0;
            
            // Process in smaller chunks for better memory management
            RegistrationDebug::where('status', 'pending')
                ->orderBy('created_at', 'asc')
                ->limit($batchSize)
                ->chunk(3, function ($registrations) use (&$processedCount, &$failedCount) {
                    // Check for signals
                    if (extension_loaded('pcntl')) {
                        pcntl_signal_dispatch();
                    }
                    
                    if ($this->shouldQuit) {
                        return false; // Stop processing
                    }
                    
                    foreach ($registrations as $reg) {
                        $lockKey = 'tenant_reg_lock_' . $reg->id;
                        
                        if (Cache::store('redis')->lock($lockKey, 300)->get()) {
                            try {
                                $reg->update(['status' => 'processing']);
                                
                                ProcessTenantRegistrationJob::dispatch($reg->id)
                                    ->onQueue('tenant-registration')
                                    ->delay(now()->addSeconds($processedCount * 2));
                                
                                Log::info("Dispatched optimized registration job for ID: {$reg->id} to queue: tenant-registration");
                                $processedCount++;
                                
                            } catch (\Exception $e) {
                                $reg->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                                $failedCount++;
                                Log::error("Failed to dispatch job for ID: {$reg->id} - " . $e->getMessage());
                                Log::error("Stack trace: " . $e->getTraceAsString());
                                Cache::lock($lockKey)->release();
                            }
                        } else {
                            $this->warn("Skipped duplicate registration for ID: {$reg->id}");
                        }
                    }
                    
                    usleep(100000); // 0.1 second
                });
                
            $this->info("Dispatched {$processedCount} registration jobs, {$failedCount} failed.");
            
            // Enhanced metrics with performance data
            Redis::setex('tenant_registration_metrics', 3600, json_encode([
                'last_run' => now(),
                'processed_count' => $processedCount,
                'failed_count' => $failedCount,
                'pending_count' => RegistrationDebug::where('status', 'pending')->count(),
                'processing_count' => RegistrationDebug::where('status', 'processing')->count(),
                'completed_count' => RegistrationDebug::where('status', 'completed')->count(),
                'total_failed' => RegistrationDebug::where('status', 'failed')->count(),
                'batch_size' => $batchSize,
            ]));
            
            if (!$daemonMode || $this->shouldQuit) {
                break;
            }
            
            // Short sleep between iterations when there's work
            sleep(5);
            
        } while (!$this->shouldQuit);
        
        $this->info("Shutting down gracefully...");
    }
    
    public function handleSignal($signal)
    {
        switch ($signal) {
            case SIGTERM:
            case SIGINT:
                $this->shouldQuit = true;
                $this->info("Received shutdown signal, finishing current batch...");
                break;
            case SIGUSR1:
                $this->displayMonitoringInfo();
                break;
        }
    }
    
    private function performHealthCheck(): bool
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
        ];
        
        foreach ($checks as $service => $status) {
            if (!$status) {
                $this->error("Health check failed for: {$service}");
                return false;
            }
        }
        
        return true;
    }

    private function checkDatabase(): bool
    {
        try {
            DB::select('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        return is_writable(storage_path('logs'));
    }
    
    private function displayEnterpriseMonitoring()
    {
        $this->info('🏢 Enterprise Tenant Registration Monitoring Dashboard');
        $this->info('==================================================');
        
        // Current status
        $pending = RegistrationDebug::where('status', 'pending')->count();
        $processing = RegistrationDebug::where('status', 'processing')->count();
        $completed = RegistrationDebug::where('status', 'completed')->count();
        $failed = RegistrationDebug::where('status', 'failed')->count();
        
        $this->info("📊 Current Status:");
        $this->info("  ⏳ Pending: {$pending}");
        $this->info("  🔄 Processing: {$processing}");
        $this->info("  ✅ Completed: {$completed}");
        $this->info("  ❌ Failed: {$failed}");
        
        // Queue health
        $queueSizes = [
            'tenant-registration' => Redis::llen('queues:tenant-registration'),
            'emails' => Redis::llen('queues:emails'),
            'default' => Redis::llen('queues:default'),
            'failed' => Redis::llen('queues:failed'),
        ];
        
        $this->info("\n🚀 Queue Status:");
        foreach ($queueSizes as $queue => $size) {
            $icon = $size > 1000 ? '⚠️' : ($size > 100 ? '📈' : '✅');
            $this->info("  {$icon} {$queue}: {$size} jobs");
        }
        
        // Performance metrics
        $hourlyMetrics = $this->getHourlyMetrics();
        if ($hourlyMetrics) {
            $this->info("\n📈 Performance (Last Hour):");
            $this->info("  🎯 Started: " . ($hourlyMetrics['started'] ?? 0));
            $this->info("  ✅ Completed: " . ($hourlyMetrics['completed'] ?? 0));
            $this->info("  ❌ Failed: " . ($hourlyMetrics['failed'] ?? 0));
            $this->info("  🚨 Permanent Failures: " . ($hourlyMetrics['permanent_failure'] ?? 0));
        }
        
        // System health
        $this->info("\n💊 System Health:");
        $this->info("  🗄️  Database: " . ($this->checkDatabase() ? '✅ Healthy' : '❌ Down'));
        $this->info("  🔴 Redis: " . ($this->checkRedis() ? '✅ Healthy' : '❌ Down'));
        $this->info("  📁 Storage: " . ($this->checkStorage() ? '✅ Writable' : '❌ Read-only'));
    }

    private function getHourlyMetrics(): ?array
    {
        $key = "tenant_registration_metrics_" . date('Y-m-d-H');
        return json_decode(Redis::get($key), true);
    }
    
    private function displayMonitoringInfo()
    {
        $metrics = json_decode(Redis::get('tenant_registration_metrics'), true);
        
        if (!$metrics) {
            $this->info('No metrics available. Run the command without --monitor first.');
            return;
        }
        
        $this->info('Tenant Registration Monitoring Dashboard');
        $this->info('=========================================');
        $this->info("Last Run: {$metrics['last_run']}");
        $this->info("Pending: {$metrics['pending_count']}");
        $this->info("Processing: {$metrics['processing_count']}");
        $this->info("Completed: {$metrics['completed_count']}");
        $this->info("Failed: {$metrics['total_failed']}");
        $this->info("Last Batch: {$metrics['processed_count']} processed, {$metrics['failed_count']} failed");
        
        // Show queue status
        $queueSize = Redis::llen('queues:tenant-registration');
        $emailQueueSize = Redis::llen('queues:emails');
        
        $this->info("Queue Status:");
        $this->info("  - Tenant Registration Queue: {$queueSize} jobs");
        $this->info("  - Email Queue: {$emailQueueSize} jobs");
    }
}

