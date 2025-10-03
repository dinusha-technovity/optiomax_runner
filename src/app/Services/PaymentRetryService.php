<?php

namespace App\Services;

use App\Models\PaymentRetryLog;
use App\Models\TenantSubscription;
use App\Models\tenants;
use App\Models\TenantPackage;
use App\Models\PaymentTransaction;
use App\Jobs\RetryPaymentJob;
use App\Jobs\SendPaymentReminderJob;
use App\Jobs\BlockTenantJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PaymentRetryService
{
    protected $stripeService;

    public function __construct(StripePaymentService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    public function createRetryLog($tenantId, $subscriptionId, $invoiceId, $amount, $failureReason = null)
    {
        try {
            $subscription = TenantSubscription::find($subscriptionId);
            $package = TenantPackage::find($subscription->package_id);
            
            $retryLog = PaymentRetryLog::create([
                'tenant_id' => $tenantId,
                'subscription_id' => $subscriptionId,
                'stripe_invoice_id' => $invoiceId,
                'retry_attempt' => 1,
                'max_retries' => $package->max_retry_attempts,
                'status' => 'pending',
                'next_retry_at' => now()->addDays($package->retry_interval_days),
                'failure_reasons' => $failureReason ? [$failureReason] : [],
                'amount' => $amount,
                'currency' => 'usd'
            ]);

            // Update tenant payment status
            $tenant = tenants::find($tenantId);
            $tenant->update([
                'payment_status' => 'past_due',
                'payment_failed_at' => now()
            ]);

            Log::info("Payment retry log created for tenant {$tenantId}, subscription {$subscriptionId}");

            return $retryLog;

        } catch (\Exception $e) {
            Log::error("Failed to create retry log: " . $e->getMessage());
            throw $e;
        }
    }

    public function processRetryPayment(PaymentRetryLog $retryLog)
    {
        try {
            $subscription = $retryLog->subscription;
            
            // Attempt to retry payment via Stripe
            $paymentResult = $this->stripeService->retryInvoicePayment($retryLog->stripe_invoice_id);

            if ($paymentResult['success']) {
                // Payment succeeded
                $this->handleSuccessfulRetry($retryLog);
                return ['success' => true, 'message' => 'Payment retry successful'];
            } else {
                // Payment failed again
                $this->handleFailedRetry($retryLog, $paymentResult['message']);
                
                if (!$retryLog->hasRetriesLeft()) {
                    // No more retries left, block tenant
                    $this->blockTenantForFailedPayment($retryLog->tenant_id);
                    return ['success' => false, 'message' => 'Payment retries exhausted, tenant blocked'];
                }
                
                return ['success' => false, 'message' => 'Payment retry failed, will try again'];
            }

        } catch (\Exception $e) {
            Log::error("Payment retry failed for log {$retryLog->id}: " . $e->getMessage());
            $this->handleFailedRetry($retryLog, $e->getMessage());
            throw $e;
        }
    }

    protected function handleSuccessfulRetry(PaymentRetryLog $retryLog)
    {
        DB::transaction(function () use ($retryLog) {
            // Update retry log
            $retryLog->update([
                'status' => 'succeeded',
                'last_retry_at' => now()
            ]);

            // Update tenant status
            $retryLog->tenant->update([
                'payment_status' => 'active',
                'payment_failed_at' => null,
                'is_tenant_blocked' => false,
                'blocked_at' => null,
                'blocking_reason' => null
            ]);

            // Update subscription status
            $retryLog->subscription->update([
                'status' => 'active'
            ]);

            // Record successful transaction
            PaymentTransaction::create([
                'tenant_id' => $retryLog->tenant_id,
                'subscription_id' => $retryLog->subscription_id,
                'stripe_invoice_id' => $retryLog->stripe_invoice_id,
                'type' => 'subscription',
                'amount' => $retryLog->amount,
                'currency' => $retryLog->currency,
                'status' => 'succeeded',
                'description' => "Retry payment - attempt {$retryLog->retry_attempt}",
                'processed_at' => now()
            ]);
        });

        Log::info("Payment retry successful for tenant {$retryLog->tenant_id}");
    }

    protected function handleFailedRetry(PaymentRetryLog $retryLog, $failureReason)
    {
        $failureReasons = $retryLog->failure_reasons ?? [];
        $failureReasons[] = [
            'attempt' => $retryLog->retry_attempt,
            'reason' => $failureReason,
            'timestamp' => now()->toISOString()
        ];

        $updateData = [
            'retry_attempt' => $retryLog->retry_attempt + 1,
            'last_retry_at' => now(),
            'failure_reasons' => $failureReasons
        ];

        if ($retryLog->hasRetriesLeft()) {
            $package = TenantPackage::find($retryLog->subscription->package_id);
            $updateData['next_retry_at'] = now()->addDays($package->retry_interval_days);
            $updateData['status'] = 'pending';
        } else {
            $updateData['status'] = 'exhausted';
            $updateData['next_retry_at'] = null;
        }

        $retryLog->update($updateData);

        Log::warning("Payment retry failed for tenant {$retryLog->tenant_id}, attempt {$retryLog->retry_attempt}");
    }

    protected function blockTenantForFailedPayment($tenantId)
    {
        $tenant = tenants::find($tenantId);
        
        $tenant->update([
            'is_tenant_blocked' => true,
            'payment_status' => 'failed',
            'blocked_at' => now(),
            'blocking_reason' => 'Payment failed after maximum retry attempts'
        ]);

        // Dispatch job to send blocking notification email
        BlockTenantJob::dispatch($tenantId)->onQueue('tenant-management');

        Log::info("Tenant {$tenantId} blocked due to payment failure");
    }

    public function processScheduledRetries()
    {
        Log::info("Starting scheduled payment retry processing...");
        
        // Get subscriptions that need renewal payment
        $dueSubs = DB::table('tenant_subscriptions as ts')
            ->join('tenant_packages as tp', 'ts.package_id', '=', 'tp.id')
            ->join('tenants as t', 'ts.tenant_id', '=', 't.id')
            ->where('ts.status', 'active')
            ->where('ts.current_period_end', '<=', now())
            ->where('tp.price', '>', 0)
            ->where('tp.is_recurring', true)
            ->whereNull('t.deleted_at')
            ->where('t.is_tenant_blocked', false)
            ->select('ts.*', 'tp.max_retry_attempts', 'tp.retry_interval_days', 'tp.name as package_name')
            ->get();

        Log::info("Found {$dueSubs->count()} subscriptions due for renewal");

        foreach ($dueSubs as $subscription) {
            $this->processSubscriptionRenewal($subscription);
        }

        // Process existing retry attempts
        $retryLogs = PaymentRetryLog::where('status', 'pending')
            ->where('next_retry_at', '<=', now())
            ->where('retry_attempt', '<', DB::raw('max_retries'))
            ->with(['tenant', 'subscription'])
            ->get();

        Log::info("Found {$retryLogs->count()} pending retries to process");

        foreach ($retryLogs as $retryLog) {
            RetryPaymentJob::dispatch($retryLog->id)->onQueue('payment-retries');
        }
    }

    private function processSubscriptionRenewal($subscription)
    {
        try {
            Log::info("Processing renewal for subscription {$subscription->id}, tenant {$subscription->tenant_id}");

            // Attempt to charge the subscription
            $paymentResult = $this->stripeService->retrySubscriptionPayment($subscription->id);

            if ($paymentResult['success']) {
                // Payment successful - update subscription period
                $newPeriodEnd = $subscription->billing_cycle === 'yearly' 
                    ? now()->addYear() 
                    : now()->addMonth();

                DB::table('tenant_subscriptions')
                    ->where('id', $subscription->id)
                    ->update([
                        'current_period_start' => now(),
                        'current_period_end' => $newPeriodEnd,
                        'status' => 'active',
                        'updated_at' => now()
                    ]);

                // Record successful payment
                DB::table('payment_transactions')->insert([
                    'tenant_id' => $subscription->tenant_id,
                    'subscription_id' => $subscription->id,
                    'type' => 'subscription',
                    'amount' => $paymentResult['amount'] ?? $subscription->amount,
                    'currency' => 'usd',
                    'status' => 'succeeded',
                    'description' => "Recurring payment for {$subscription->package_name}",
                    'processed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info("Subscription renewal successful for tenant {$subscription->tenant_id}");

            } else {
                // Payment failed - create retry log
                $this->createRetryLogForFailedRenewal($subscription, $paymentResult['message']);
            }

        } catch (\Exception $e) {
            Log::error("Subscription renewal failed for {$subscription->id}: " . $e->getMessage());
            $this->createRetryLogForFailedRenewal($subscription, $e->getMessage());
        }
    }

    private function createRetryLogForFailedRenewal($subscription, $failureReason)
    {
        try {
            $retryLog = PaymentRetryLog::create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'retry_attempt' => 1,
                'max_retries' => $subscription->max_retry_attempts,
                'status' => 'pending',
                'next_retry_at' => now()->addDays($subscription->retry_interval_days),
                'failure_reasons' => [$failureReason],
                'amount' => $subscription->amount,
                'currency' => 'usd'
            ]);

            // Update tenant payment status
            DB::table('tenants')
                ->where('id', $subscription->tenant_id)
                ->update([
                    'payment_status' => 'past_due',
                    'payment_failed_at' => now(),
                    'updated_at' => now()
                ]);

            // Update subscription status
            DB::table('tenant_subscriptions')
                ->where('id', $subscription->id)
                ->update([
                    'status' => 'past_due',
                    'updated_at' => now()
                ]);

            Log::info("Created retry log for failed renewal: subscription {$subscription->id}, tenant {$subscription->tenant_id}");

        } catch (\Exception $e) {
            Log::error("Failed to create retry log: " . $e->getMessage());
        }
    }

    public function sendPaymentReminders()
    {
        // Get subscriptions expiring in 7 days that haven't been reminded recently
        $reminderSubs = DB::table('tenant_subscriptions as ts')
            ->join('tenants as t', 'ts.tenant_id', '=', 't.id')
            ->join('tenant_packages as tp', 'ts.package_id', '=', 'tp.id')
            ->where('ts.status', 'active')
            ->where('ts.current_period_end', '>', now())
            ->where('ts.current_period_end', '<=', now()->addDays(7))
            ->where('tp.price', '>', 0)
            ->where('tp.is_recurring', true)
            ->where(function($query) {
                $query->whereNull('t.last_payment_reminder_sent')
                      ->orWhere('t.last_payment_reminder_sent', '<', now()->subDays(3));
            })
            ->whereNull('t.deleted_at')
            ->where('t.is_tenant_blocked', false)
            ->select('ts.*', 'tp.name as package_name')
            ->get();

        Log::info("Sending payment reminders to {$reminderSubs->count()} tenants");

        foreach ($reminderSubs as $subscription) {
            // Create a temporary retry log for reminder purposes
            $tempRetryLog = new PaymentRetryLog([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'amount' => $subscription->amount,
                'next_retry_at' => $subscription->current_period_end
            ]);
            $tempRetryLog->id = 'reminder_' . $subscription->id;

            SendPaymentReminderJob::dispatch($tempRetryLog->id)->onQueue('notifications');

            // Update reminder sent timestamp
            DB::table('tenants')
                ->where('id', $subscription->tenant_id)
                ->update([
                    'last_payment_reminder_sent' => now(),
                    'updated_at' => now()
                ]);
        }
    }
}
