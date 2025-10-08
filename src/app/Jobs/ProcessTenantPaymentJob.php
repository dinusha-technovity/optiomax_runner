<?php

namespace App\Jobs;

use App\Models\RegistrationDebug;
use App\Models\tenants;
use App\Models\User;
use App\Models\TenantSubscription;
use App\Models\PaymentTransaction;
use App\Services\StripePaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessTenantPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $registrationDebugId;
    public $timeout = 120;
    public $tries = 3;

    public function __construct($registrationDebugId)
    {
        $this->registrationDebugId = $registrationDebugId;
    }

    public function handle()
    {
        $reg = RegistrationDebug::find($this->registrationDebugId);
        if (!$reg) {
            Log::warning("Registration record not found for payment processing: {$this->registrationDebugId}");
            return;
        }

        $validatedUser = $reg->validated_user;
        $tenantId = $validatedUser['tenant_id'] ?? null;
        
        if (!$tenantId) {
            Log::error("Tenant ID not found in registration data for payment processing: {$reg->id}");
            $reg->update(['status' => 'payment_failed', 'error_message' => 'Tenant ID not found']);
            return;
        }

        $tenant = tenants::find($tenantId);
        if (!$tenant) {
            Log::error("Tenant not found for payment processing: {$tenantId}");
            $reg->update(['status' => 'payment_failed', 'error_message' => 'Tenant not found']);
            return;
        }

        Log::info("Starting payment processing for registration ID: {$reg->id}, Tenant ID: {$tenantId}");

        try {
            // Get package details using selected_package_id from RegistrationDebug
            $selectedPackageId = $reg->selected_package_id;
            
            if (!$selectedPackageId) {
                Log::error("Selected package ID not found in registration debug: {$reg->id}");
                $reg->update(['status' => 'payment_failed', 'error_message' => 'Selected package ID not found']);
                return;
            }

            $package = DB::table('tenant_packages')
                ->where('id', $selectedPackageId)
                ->where('isactive', true)
                ->whereNull('deleted_at')
                ->first();

            if (!$package) {
                Log::error("Package with ID '{$selectedPackageId}' not found or not active. Registration ID: {$reg->id}");
                $reg->update(['status' => 'payment_failed', 'error_message' => "Package ID {$selectedPackageId} not found"]);
                return;
            }

            Log::info("Processing payment for package: {$package->name} (ID: {$package->id}), Price: {$package->price}");

            // Check if payment is required
            if ($package->price <= 0 || !$package->is_recurring) {
                Log::info("Package '{$package->name}' is free or non-recurring. Creating free subscription.");
                $this->createFreeSubscription($tenant, $package, $reg);
                return;
            }

            $owner_user = User::find($tenant->owner_user);
            if (!$owner_user) {
                Log::error("Owner user not found for tenant ID: {$tenantId}");
                $reg->update(['status' => 'payment_failed', 'error_message' => 'Owner user not found']);
                return;
            }

            // Extract payment details from tenant record
            $customerId = $owner_user->stripe_customer_id;
            $paymentMethodId = $owner_user->stripe_payment_method_id;

            Log::info("Payment details check - Customer ID: " . ($customerId ? 'found' : 'missing') . 
                     ", Payment Method ID: " . ($paymentMethodId ? 'found' : 'missing') . ", Owner User: " . ($owner_user->name ?? 'unknown'));

            if (!$customerId || !$paymentMethodId) {
                Log::warning("Payment details not found for paid package '{$package->name}'. Package price: {$package->price}");
                Log::info("Creating free subscription instead since payment details are missing");
                $this->createFreeSubscription($tenant, $package, $reg);
                return;
            }

            // Process payment with found details
            $paymentData = [
                'customerId' => $customerId,
                'paymentMethodId' => $paymentMethodId
            ];
            
            $this->processPayment($tenant, $package, $paymentData, $reg);

        } catch (\Exception $e) {
            Log::error("Payment processing failed for registration ID {$reg->id}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            $reg->update([
                'status' => 'payment_failed',
                'error_message' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    private function processPayment($tenant, $package, $validatedUser, $reg)
    {
        DB::beginTransaction();
        
        try {
            $stripeService = new StripePaymentService();
            $billingCycle = $package->type === 'year' ? 'yearly' : 'monthly';

            Log::info("Creating Stripe subscription for tenant {$tenant->id}, package {$package->id}, billing cycle: {$billingCycle}");

            // Create Stripe subscription with real payment processing
            $subscriptionResult = $stripeService->createSubscription(
                $tenant->id,
                $package->id,
                $validatedUser['customerId'],
                $validatedUser['paymentMethodId'],
                $billingCycle
            );

            if (!$subscriptionResult['success']) {
                throw new \Exception('Stripe subscription creation failed: ' . $subscriptionResult['message']);
            }

            // Update tenant with subscription details
            $tenant->update([
                'stripe_subscription_id' => $subscriptionResult['subscription_id'],
                'payment_status' => 'active'
            ]);

            // Handle setup fee if applicable
            if ($package->setup_fee > 0) {
                $this->processSetupFee($tenant, $package, $validatedUser, $stripeService);
            }

            // Create initial payment transaction record
            $this->createInitialPaymentTransaction($tenant, $package, $subscriptionResult, $billingCycle);

            DB::commit();

            $reg->update([
                'status' => 'completed_with_payment',
                'error_message' => null
            ]);

            Log::info("Payment processing completed successfully for registration ID: {$reg->id}. Subscription ID: {$subscriptionResult['subscription_id']}");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Payment transaction failed for registration ID {$reg->id}: " . $e->getMessage());
            throw $e;
        }
    }

    private function createInitialPaymentTransaction($tenant, $package, $subscriptionResult, $billingCycle)
    {
        try {
            // Get the subscription ID from the result
            $subscriptionId = $subscriptionResult['local_subscription_id'] ?? null;
            
            // Record the initial payment transaction
            DB::table('payment_transactions')->insert([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscriptionId,
                'stripe_payment_intent_id' => $subscriptionResult['stripe_payment_intent_id'] ?? null,
                'stripe_invoice_id' => $subscriptionResult['stripe_invoice_id'] ?? null,
                'type' => 'subscription',
                'amount' => $package->price,
                'currency' => 'usd',
                'status' => 'succeeded',
                'description' => "Initial {$billingCycle} subscription payment for {$package->name}",
                'stripe_response' => json_encode($subscriptionResult),
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("Initial payment transaction recorded for tenant {$tenant->id}, amount: {$package->price}");

        } catch (\Exception $e) {
            Log::warning("Failed to create initial payment transaction: " . $e->getMessage());
        }
    }

    private function processSetupFee($tenant, $package, $validatedUser, $stripeService)
    {
        try {
            Log::info("Processing setup fee of {$package->setup_fee} for tenant {$tenant->id}");
            
            $setupFeeResult = $stripeService->chargeSetupFee(
                $tenant->id,
                $validatedUser['customerId'],
                $validatedUser['paymentMethodId'],
                $package->setup_fee
            );

            if ($setupFeeResult['success']) {
                Log::info("Setup fee charged successfully for tenant {$tenant->id}: {$package->setup_fee}");
            } else {
                Log::warning("Setup fee charge failed for tenant {$tenant->id}: " . $setupFeeResult['message']);
            }

        } catch (\Exception $e) {
            Log::warning("Setup fee processing failed for tenant {$tenant->id}: " . $e->getMessage());
        }
    }

    private function createFreeSubscription($tenant, $package, $reg)
    {
        try {
            DB::beginTransaction();

            $billingCycle = $package->type === 'year' ? 'yearly' : 'monthly';
            
            // Create a local subscription record for free plans
            $subscription = DB::table('tenant_subscriptions')->insertGetId([
                'tenant_id' => $tenant->id,
                'package_id' => $package->id,
                'stripe_customer_id' => $tenant->stripe_customer_id ?? 'free_plan',
                'stripe_payment_method_id' => $tenant->stripe_payment_method_id ?? 'none',
                'billing_cycle' => $billingCycle,
                'status' => 'active',
                'amount' => 0.00,
                'current_period_start' => now(),
                'current_period_end' => $package->type === 'year' ? now()->addYear() : now()->addMonth(),
                'trial_end' => $package->trial_days > 0 ? now()->addDays($package->trial_days) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $tenant->update(['payment_status' => 'active']);

            DB::commit();

            $reg->update([
                'status' => 'completed_free_plan',
                'error_message' => null
            ]);

            Log::info("Free subscription created for tenant {$tenant->id}, registration ID: {$reg->id}, subscription ID: {$subscription}");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create free subscription for registration ID {$reg->id}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        $reg = RegistrationDebug::find($this->registrationDebugId);
        if ($reg) {
            $reg->update([
                'status' => 'payment_failed',
                'error_message' => $exception->getMessage()
            ]);
        }
        
        Log::error("ProcessTenantPaymentJob failed permanently for ID: {$this->registrationDebugId} - " . $exception->getMessage());
    }
}
