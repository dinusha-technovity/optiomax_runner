<?php

namespace App\Jobs;

use App\Models\TenantSubscription;
use App\Models\PaymentTransaction;
use App\Models\PaymentRetryLog;
use App\Models\User;
use App\Services\StripePaymentService;
use App\Services\InvoiceService;
use App\Mail\PaymentSuccessMail;
use App\Mail\PaymentFailedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessRecurringPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $subscriptionId;
    public $timeout = 180;
    public $tries = 3;

    public function __construct($subscriptionId)
    {
        $this->subscriptionId = $subscriptionId;
    }

    public function handle()
    {
        $subscription = TenantSubscription::with(['tenant', 'package'])->find($this->subscriptionId);
        
        if (!$subscription) {
            Log::warning("Subscription not found for recurring payment: {$this->subscriptionId}");
            return;
        }

        $tenant = $subscription->tenant;
        if (!$tenant) {
            Log::error("Tenant not found for subscription {$this->subscriptionId}");
            return;
        }

        Log::info("Processing recurring payment for subscription {$this->subscriptionId}, tenant {$tenant->id}");

        try {
            $stripeService = new StripePaymentService();
            
            // Process the recurring payment through Stripe
            $paymentResult = $stripeService->processRecurringPayment(
                $subscription->stripe_subscription_id,
                $tenant->id
            );

            if ($paymentResult['success']) {
                $this->handleSuccessfulPayment($subscription, $paymentResult);
            } else {
                $this->handleFailedPayment($subscription, $paymentResult);
            }

        } catch (\Exception $e) {
            Log::error("Recurring payment job failed for subscription {$this->subscriptionId}: " . $e->getMessage());
            $this->handleFailedPayment($subscription, [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'job_exception'
            ]);
        }
    }

    private function handleSuccessfulPayment($subscription, $paymentResult)
    {
        DB::beginTransaction();
        
        try {
            // Record successful payment transaction
            PaymentTransaction::create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'stripe_payment_intent_id' => $paymentResult['payment_intent_id'],
                'stripe_invoice_id' => $paymentResult['invoice_id'],
                'type' => 'recurring_subscription',
                'amount' => $paymentResult['amount_paid'],
                'currency' => 'usd',
                'status' => 'succeeded',
                'description' => "Recurring {$subscription->billing_cycle} payment",
                'stripe_response' => json_encode($paymentResult),
                'processed_at' => now(),
            ]);

            // Update subscription period
            $nextPeriodEnd = $subscription->billing_cycle === 'yearly' 
                ? $subscription->current_period_end->addYear()
                : $subscription->current_period_end->addMonth();

            $subscription->update([
                'current_period_start' => $subscription->current_period_end,
                'current_period_end' => $nextPeriodEnd,
                'status' => 'active',
            ]);

            // Update tenant payment status
            $subscription->tenant->update([
                'payment_status' => 'active',
                'last_payment_date' => now(),
            ]);

            // Clear any existing retry logs for this subscription
            PaymentRetryLog::where('subscription_id', $subscription->id)
                ->where('status', 'pending')
                ->update(['status' => 'resolved']);

            DB::commit();

            // Send success notification and invoice
            $this->sendPaymentSuccessNotification($subscription, $paymentResult);

            Log::info("Recurring payment successful for subscription {$subscription->id}: ${$paymentResult['amount_paid']}");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to handle successful payment for subscription {$subscription->id}: " . $e->getMessage());
            throw $e;
        }
    }

    private function handleFailedPayment($subscription, $paymentResult)
    {
        DB::beginTransaction();
        
        try {
            // Record failed payment transaction
            PaymentTransaction::create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'stripe_payment_intent_id' => $paymentResult['payment_intent_id'] ?? null,
                'stripe_invoice_id' => $paymentResult['invoice_id'] ?? null,
                'type' => 'recurring_subscription',
                'amount' => $subscription->amount,
                'currency' => 'usd',
                'status' => 'failed',
                'description' => "Failed recurring {$subscription->billing_cycle} payment",
                'failure_reason' => $paymentResult['message'],
                'stripe_response' => json_encode($paymentResult),
                'processed_at' => now(),
            ]);

            // Update subscription status
            $subscription->update([
                'status' => 'past_due',
            ]);

            // Update tenant payment status
            $subscription->tenant->update([
                'payment_status' => 'past_due',
                'last_payment_failure' => now(),
            ]);

            // Create or update payment retry log
            $this->createPaymentRetryLog($subscription, $paymentResult);

            DB::commit();

            // Send failure notification
            $this->sendPaymentFailureNotification($subscription, $paymentResult);

            Log::warning("Recurring payment failed for subscription {$subscription->id}: " . $paymentResult['message']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to handle failed payment for subscription {$subscription->id}: " . $e->getMessage());
            throw $e;
        }
    }

    private function createPaymentRetryLog($subscription, $paymentResult)
    {
        $package = $subscription->package;
        $existingRetryLog = PaymentRetryLog::where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->first();

        if ($existingRetryLog) {
            // Update existing retry log
            $existingRetryLog->update([
                'retry_attempt' => $existingRetryLog->retry_attempt + 1,
                'last_failure_reason' => $paymentResult['message'],
                'failure_reasons' => array_merge(
                    $existingRetryLog->failure_reasons ?? [], 
                    [$paymentResult['message']]
                ),
                'next_retry_date' => now()->addDays($package->retry_interval_days),
                'updated_at' => now(),
            ]);
        } else {
            // Create new retry log
            PaymentRetryLog::create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'package_id' => $subscription->package_id,
                'amount' => $subscription->amount,
                'currency' => 'usd',
                'retry_attempt' => 1,
                'max_retry_attempts' => $package->max_retry_attempts,
                'retry_interval_days' => $package->retry_interval_days,
                'grace_period_days' => $package->grace_period_days,
                'status' => 'pending',
                'last_failure_reason' => $paymentResult['message'],
                'failure_reasons' => [$paymentResult['message']],
                'next_retry_date' => now()->addDays($package->retry_interval_days),
                'grace_period_end' => now()->addDays($package->grace_period_days),
                'stripe_error_code' => $paymentResult['error_code'] ?? null,
                'decline_code' => $paymentResult['decline_code'] ?? null,
            ]);
        }
    }

    private function sendPaymentSuccessNotification($subscription, $paymentResult)
    {
        try {
            $owner = User::find($subscription->tenant->owner_user);
            if (!$owner) {
                Log::warning("Owner user not found for payment success notification: tenant {$subscription->tenant_id}");
                return;
            }

            // Generate invoice
            $invoiceService = new InvoiceService();
            $invoiceData = [
                'tenant' => $subscription->tenant,
                'subscription' => $subscription,
                'payment_result' => $paymentResult,
                'owner_user' => $owner,
                'invoice_number' => $this->generateInvoiceNumber($subscription->tenant_id),
                'invoice_date' => now(),
                'due_date' => now(), // Already paid
            ];

            $invoicePdf = $invoiceService->generateInvoicePDF($invoiceData);

            Mail::to($owner->email)->send(new PaymentSuccessMail(
                $owner,
                $subscription->tenant,
                $subscription,
                $paymentResult,
                $invoicePdf
            ));

            Log::info("Payment success notification sent to {$owner->email} for tenant {$subscription->tenant_id}");

        } catch (\Exception $e) {
            Log::error("Failed to send payment success notification for tenant {$subscription->tenant_id}: " . $e->getMessage());
        }
    }

    private function sendPaymentFailureNotification($subscription, $paymentResult)
    {
        try {
            $owner = User::find($subscription->tenant->owner_user);
            if (!$owner) {
                Log::warning("Owner user not found for payment failure notification: tenant {$subscription->tenant_id}");
                return;
            }

            Mail::to($owner->email)->send(new PaymentFailedMail(
                $owner,
                $subscription->tenant,
                $subscription->package,
                $paymentResult['message'],
                $subscription
            ));

            Log::info("Payment failure notification sent to {$owner->email} for tenant {$subscription->tenant_id}");

        } catch (\Exception $e) {
            Log::error("Failed to send payment failure notification for tenant {$subscription->tenant_id}: " . $e->getMessage());
        }
    }

    private function generateInvoiceNumber($tenantId)
    {
        $prefix = 'REC';
        $date = now()->format('Ymd');
        $sequence = str_pad($tenantId, 6, '0', STR_PAD_LEFT);
        $random = strtoupper(substr(md5(uniqid()), 0, 4));
        return "{$prefix}-{$date}-{$sequence}-{$random}";
    }

    public function failed(\Throwable $exception)
    {
        Log::error("ProcessRecurringPaymentJob failed permanently for subscription {$this->subscriptionId}: " . $exception->getMessage());
        
        // Mark subscription as having issues
        $subscription = TenantSubscription::find($this->subscriptionId);
        if ($subscription) {
            $subscription->update(['status' => 'past_due']);
            
            // Create payment retry log if it doesn't exist
            $this->createPaymentRetryLog($subscription, [
                'message' => 'Payment job failed: ' . $exception->getMessage(),
                'error_code' => 'job_failure'
            ]);
        }
    }
}
