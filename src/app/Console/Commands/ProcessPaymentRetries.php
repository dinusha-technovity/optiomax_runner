<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PaymentRetryService;
use Illuminate\Support\Facades\Log;

class ProcessPaymentRetries extends Command
{
    protected $signature = 'payment:process-retries {--dry-run : Show what would be processed without making changes}';
    protected $description = 'Process scheduled payment retries and send reminders for recurring subscriptions';

    public function handle(PaymentRetryService $paymentRetryService)
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('Running in DRY RUN mode - no actual payments will be processed');
        }

        $this->info('Processing payment retries and reminders...');
        Log::info('Payment retry process started via command', ['dry_run' => $isDryRun]);

        try {
            if (!$isDryRun) {
                // Process scheduled retries for failed payments
                $paymentRetryService->processScheduledRetries();
                
                // Send payment reminders for upcoming renewals
                $paymentRetryService->sendPaymentReminders();
            } else {
                $this->info('DRY RUN: Would process scheduled retries and send reminders');
            }

            $this->info('Payment processing completed successfully.');
            Log::info('Payment retry process completed successfully');

        } catch (\Exception $e) {
            $this->error('Payment processing failed: ' . $e->getMessage());
            Log::error('Payment retry process failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
