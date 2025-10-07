<?php

namespace App\Jobs;

use App\Models\PaymentRetryLog;
use App\Services\PaymentRetryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $retryLogId;
    public $timeout = 120;
    public $tries = 2;

    public function __construct($retryLogId)
    {
        $this->retryLogId = $retryLogId;
    }

    public function handle(PaymentRetryService $paymentRetryService)
    {
        $retryLog = PaymentRetryLog::find($this->retryLogId);
        
        if (!$retryLog) {
            Log::warning("Payment retry log not found: {$this->retryLogId}");
            return;
        }

        if (!$retryLog->isReadyForRetry()) {
            Log::info("Payment retry log {$this->retryLogId} not ready for retry");
            return;
        }

        Log::info("Processing payment retry for tenant {$retryLog->tenant_id}, attempt {$retryLog->retry_attempt}");

        try {
            $result = $paymentRetryService->processRetryPayment($retryLog);
            Log::info("Payment retry result for log {$this->retryLogId}: " . json_encode($result));
        } catch (\Exception $e) {
            Log::error("Payment retry job failed for log {$this->retryLogId}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        $retryLog = PaymentRetryLog::find($this->retryLogId);
        if ($retryLog) {
            $retryLog->update([
                'status' => 'failed',
                'failure_reasons' => array_merge($retryLog->failure_reasons ?? [], [
                    'job_failed' => $exception->getMessage()
                ])
            ]);
        }
        
        Log::error("RetryPaymentJob failed permanently for log {$this->retryLogId}: " . $exception->getMessage());
    }
}
