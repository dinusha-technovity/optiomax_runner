<?php

namespace App\Jobs;

use App\Models\RegistrationDebug;
use App\Models\tenants;
use App\Models\User;
use App\Models\TenantSubscription;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionAddon;
use App\Services\StripePaymentService;
use App\Services\InvoiceService;
use App\Mail\PaymentReceiptMail;
use App\Mail\PaymentFailedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessTenantPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $registrationDebugId;
    public $timeout = 180;
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

            $owner_user = User::find($tenant->owner_user);
            if (!$owner_user) {
                Log::error("Owner user not found for tenant ID: {$tenantId}");
                $reg->update(['status' => 'payment_failed', 'error_message' => 'Owner user not found']);
                return;
            }

            // Get selected addons from registration
            $selectedAddons = $reg->selected_addons ?? [];
            
            Log::info("Processing payment for package: {$package->name} (ID: {$package->id}) with " . count($selectedAddons) . " addons");

            // Determine billing cycle
            $billingCycle = $this->determineBillingCycle($package, $reg);
            
            // Calculate total pricing including addons
            $pricingDetails = $this->calculateTotalPricing($package, $selectedAddons, $billingCycle);
            
            // Check if payment is required
            if ($pricingDetails['total_amount'] <= 0) {
                Log::info("Package '{$package->name}' with addons is free. Creating free subscription.");
                $this->createFreeSubscription($tenant, $package, $selectedAddons, $reg, $billingCycle);
                return;
            }

            // Extract payment details from owner user
            $customerId = $owner_user->stripe_customer_id;
            $paymentMethodId = $owner_user->stripe_payment_method_id;

            Log::info("Payment details check - Customer ID: " . ($customerId ? 'found' : 'missing') . 
                     ", Payment Method ID: " . ($paymentMethodId ? 'found' : 'missing'));

            if (!$customerId || !$paymentMethodId) {
                Log::warning("Payment details not found for paid package. Creating as pending payment.");
                $this->createPendingPaymentSubscription($tenant, $package, $selectedAddons, $reg, $billingCycle, $pricingDetails);
                return;
            }

            // Process payment with found details
            $paymentData = [
                'customerId' => $customerId,
                'paymentMethodId' => $paymentMethodId
            ];
            
            $this->processPayment($tenant, $package, $selectedAddons, $paymentData, $reg, $billingCycle, $pricingDetails);

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

    private function determineBillingCycle($package, $reg)
    {
        // Check registration preferences first
        if (isset($reg->billing_preference)) {
            return $reg->billing_preference === 'yearly' ? 'yearly' : 'monthly';
        }
        
        // Default based on package billing type
        if ($package->billing_type === 'yearly') {
            return 'yearly';
        } elseif ($package->billing_type === 'monthly') {
            return 'monthly';
        }
        
        // Default to monthly for 'both' billing type
        return 'monthly';
    }

    private function calculateTotalPricing($package, $selectedAddons, $billingCycle)
    {
        $isYearly = $billingCycle === 'yearly';
        $basePrice = $isYearly ? $package->base_price_yearly : $package->base_price_monthly;
        $setupFee = $package->setup_fee ?? 0;
        
        $addonDetails = [];
        $addonTotal = 0;
        
        // Calculate addon costs
        foreach ($selectedAddons as $addonSelection) {
            $addon = DB::table('package_addons')
                ->where('id', $addonSelection['addon_id'])
                ->where('isactive', true)
                ->first();
                
            if (!$addon) {
                continue;
            }
            
            $quantity = $addonSelection['quantity'] ?? 1;
            $addonPrice = $isYearly ? $addon->price_yearly : $addon->price_monthly;
            $addonCost = $addonPrice * $quantity;
            $addonTotal += $addonCost;
            
            $addonDetails[] = [
                'addon_id' => $addon->id,
                'name' => $addon->name,
                'slug' => $addon->slug,
                'quantity' => $quantity,
                'unit_price' => $addonPrice,
                'total_price' => $addonCost,
                'billing_cycle' => $billingCycle,
                'boost_values' => json_decode($addon->boost_values, true),
            ];
        }
        
        return [
            'base_price' => $basePrice,
            'addon_total' => $addonTotal,
            'setup_fee' => $setupFee,
            'subtotal' => $basePrice + $addonTotal,
            'total_amount' => $basePrice + $addonTotal + $setupFee,
            'billing_cycle' => $billingCycle,
            'addon_details' => $addonDetails,
        ];
    }

    private function processPayment($tenant, $package, $selectedAddons, $paymentData, $reg, $billingCycle, $pricingDetails)
    {
        DB::beginTransaction();
        
        try {
            $stripeService = new StripePaymentService();
            $invoiceService = new InvoiceService();

            Log::info("Creating Stripe subscription for tenant {$tenant->id}, package {$package->id}, billing cycle: {$billingCycle}");

            // Create Stripe subscription with real payment processing
            $subscriptionResult = $stripeService->createSubscriptionWithAddons(
                $tenant->id,
                $package->id,
                $paymentData['customerId'],
                $paymentData['paymentMethodId'],
                $billingCycle,
                $pricingDetails['addon_details']
            );

            if (!$subscriptionResult['success']) {
                throw new \Exception('Stripe subscription creation failed: ' . $subscriptionResult['message']);
            }

            // Create local subscription record
            $subscription = $this->createSubscriptionRecord($tenant, $package, $subscriptionResult, $billingCycle, $pricingDetails, $paymentData);

            // Create addon records
            $this->createAddonRecords($subscription, $pricingDetails['addon_details']);

            // Handle setup fee if applicable and not local-only
            $setupFeeTransaction = null;
            if ($pricingDetails['setup_fee'] > 0 && !isset($subscriptionResult['metadata']['local_only'])) {
                $setupFeeTransaction = $this->processSetupFee($tenant, $package, $paymentData, $stripeService, $pricingDetails['setup_fee']);
            }

            // Create initial payment transaction record
            $paymentTransaction = $this->createInitialPaymentTransaction($tenant, $subscription, $subscriptionResult, $pricingDetails);

            // Generate and send invoice
            $this->generateAndSendInvoice($tenant, $subscription, $pricingDetails, $setupFeeTransaction, $invoiceService);

            // Update tenant with subscription details
            $tenant->update([
                'stripe_subscription_id' => $subscriptionResult['subscription_id'],
                'payment_status' => isset($subscriptionResult['metadata']['local_only']) ? 'local_only' : 'active'
            ]);

            DB::commit();

            $status = isset($subscriptionResult['metadata']['local_only']) ? 'completed_local_subscription' : 'completed_with_payment';
            $reg->update([
                'status' => $status,
                'error_message' => null
            ]);

            Log::info("Payment processing completed successfully for registration ID: {$reg->id}. Subscription ID: {$subscriptionResult['subscription_id']}");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Payment transaction failed for registration ID {$reg->id}: " . $e->getMessage());
            
            // Send payment failure notification with error handling
            try {
                $this->sendPaymentFailureNotification($tenant, $package, $e);
            } catch (\Exception $mailException) {
                Log::error("Failed to send payment failure notification: " . $mailException->getMessage());
            }
            
            throw $e;
        }
    }

    private function createSubscriptionRecord($tenant, $package, $subscriptionResult, $billingCycle, $pricingDetails, $paymentData)
    {
        $owner_user = User::find($tenant->owner_user);

        // Extract customer ID and payment method ID from the tenant or paymentData
        $customerId = $paymentData['customerId'] ?? $owner_user->stripe_customer_id;
        $paymentMethodId = $paymentData['paymentMethodId'] ?? $owner_user->stripe_payment_method_id;

        // Ensure we have the required Stripe IDs
        if (!$customerId) {
            throw new \Exception("Stripe customer ID is missing for tenant {$tenant->id}");
        }

        if (!$paymentMethodId) {
            throw new \Exception("Stripe payment method ID is missing for tenant {$tenant->id}");
        }

        Log::info("Creating subscription record with customer ID: {$customerId}, payment method ID: {$paymentMethodId}");

        return DB::table('tenant_subscriptions')->insertGetId([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'stripe_customer_id' => $customerId,
            'stripe_subscription_id' => $subscriptionResult['subscription_id'],
            'stripe_payment_method_id' => $paymentMethodId,
            'billing_cycle' => $billingCycle,
            'status' => 'active',
            'amount' => $pricingDetails['subtotal'],
            'current_period_start' => $subscriptionResult['current_period_start'] ?? now(),
            'current_period_end' => $subscriptionResult['current_period_end'] ?? ($billingCycle === 'yearly' ? now()->addYear() : now()->addMonth()),
            'trial_end' => $package->trial_days > 0 ? now()->addDays($package->trial_days) : null,
            'metadata' => json_encode([
                'package_name' => $package->name,
                'billing_cycle' => $billingCycle,
                'addon_count' => count($pricingDetails['addon_details']),
                'setup_fee' => number_format($pricingDetails['setup_fee'], 2),
                'legal_version' => $package->legal_version ?? '1.0',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAddonRecords($subscriptionId, $addonDetails)
    {
        foreach ($addonDetails as $addon) {
            DB::table('subscription_addons')->insert([
                'subscription_id' => $subscriptionId,
                'addon_id' => $addon['addon_id'],
                'quantity' => $addon['quantity'],
                'unit_price_monthly' => $addon['unit_price'],
                'unit_price_yearly' => $addon['unit_price'],
                'total_price_monthly' => $addon['total_price'],
                'total_price_yearly' => $addon['total_price'],
                'billing_cycle' => $addon['billing_cycle'] ?? 'monthly',
                'status' => 'active',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createInitialPaymentTransaction($tenant, $subscription, $subscriptionResult, $pricingDetails)
    {
        return DB::table('payment_transactions')->insertGetId([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription,
            'stripe_payment_intent_id' => $subscriptionResult['stripe_payment_intent_id'] ?? null,
            'stripe_invoice_id' => $subscriptionResult['stripe_invoice_id'] ?? null,
            'type' => 'subscription_with_addons',
            'amount' => $pricingDetails['subtotal'],
            'currency' => 'usd',
            'status' => 'succeeded',
            'description' => "Initial {$pricingDetails['billing_cycle']} subscription payment with " . count($pricingDetails['addon_details']) . " addons",
            'stripe_response' => json_encode($subscriptionResult),
            'metadata' => json_encode([
                'billing_cycle' => $pricingDetails['billing_cycle'],
                'base_price' => number_format($pricingDetails['base_price'], 2),
                'addon_total' => $pricingDetails['addon_total'],
                'addon_count' => count($pricingDetails['addon_details']),
                'package_id' => $tenant->id, // Fix: should be package ID, not tenant ID
                'tenant_id' => $tenant->id
            ]),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function processSetupFee($tenant, $package, $paymentData, $stripeService, $setupFee)
    {
        try {
            Log::info("Processing setup fee of {$setupFee} for tenant {$tenant->id}");
            
            $setupFeeResult = $stripeService->chargeSetupFee(
                $tenant->id,
                $paymentData['customerId'],
                $paymentData['paymentMethodId'],
                $setupFee,
                "Setup fee for {$package->name} package"
            );

            if ($setupFeeResult['success']) {
                // Record setup fee transaction
                $transactionId = DB::table('payment_transactions')->insertGetId([
                    'tenant_id' => $tenant->id,
                    'subscription_id' => null,
                    'stripe_payment_intent_id' => $setupFeeResult['payment_intent_id'] ?? null,
                    'type' => 'setup_fee',
                    'amount' => $setupFee,
                    'currency' => 'usd',
                    'status' => 'succeeded',
                    'description' => "Setup fee for {$package->name} package",
                    'stripe_response' => json_encode($setupFeeResult),
                    'processed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                Log::info("Setup fee charged successfully for tenant {$tenant->id}: {$setupFee}");
                return $transactionId;
            } else {
                Log::warning("Setup fee charge failed for tenant {$tenant->id}: " . $setupFeeResult['message']);
                return null;
            }

        } catch (\Exception $e) {
            Log::warning("Setup fee processing failed for tenant {$tenant->id}: " . $e->getMessage());
            return null;
        }
    }

    private function generateAndSendInvoice($tenant, $subscription, $pricingDetails, $setupFeeTransaction, $invoiceService)
    {
        try {
            $owner_user = User::find($tenant->owner_user);
            if (!$owner_user) {
                Log::warning("Owner user not found for invoice generation: tenant {$tenant->id}");
                return;
            }

            // Generate invoice PDF - fix the date issue
            $invoiceData = [
                'invoice_number' => $this->generateInvoiceNumber($tenant->id),
                'invoice_date' => now(), // Use Carbon instance instead of string
                'tenant' => $tenant,
                'pricing_details' => $pricingDetails,
                'setup_fee_transaction' => $setupFeeTransaction,
                'owner_user' => $owner_user,
            ];

            $invoicePdf = $invoiceService->generateInvoicePDF($invoiceData);

            // Send payment receipt email with PDF
            Mail::to($owner_user->email)->send(new PaymentReceiptMail(
                $owner_user,
                $tenant,
                $invoiceData,
                $invoicePdf
            ));

            Log::info("Invoice generated and sent to {$owner_user->email} for tenant {$tenant->id}");

        } catch (\Exception $e) {
            Log::error("Failed to generate/send invoice for tenant {$tenant->id}: " . $e->getMessage());
        }
    }

    private function generateInvoiceNumber($tenantId)
    {
        $prefix = 'INV';
        $date = now()->format('Ymd');
        $sequence = str_pad($tenantId, 6, '0', STR_PAD_LEFT);
        return "{$prefix}-{$date}-{$sequence}";
    }

    private function createFreeSubscription($tenant, $package, $selectedAddons, $reg, $billingCycle)
    {
        try {
            DB::beginTransaction();

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
                'current_period_end' => $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                'trial_end' => $package->trial_days > 0 ? now()->addDays($package->trial_days) : null,
                'metadata' => json_encode([
                    'package_name' => $package->name,
                    'billing_cycle' => $billingCycle,
                    'addon_count' => count($selectedAddons),
                    'is_free' => true,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create addon records for free addons
            if (!empty($selectedAddons)) {
                $pricingDetails = $this->calculateTotalPricing($package, $selectedAddons, $billingCycle);
                $this->createAddonRecords($subscription, $pricingDetails['addon_details']);
            }

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

    private function createPendingPaymentSubscription($tenant, $package, $selectedAddons, $reg, $billingCycle, $pricingDetails)
    {
        try {
            DB::beginTransaction();

            // Create subscription with pending payment status
            $subscription = DB::table('tenant_subscriptions')->insertGetId([
                'tenant_id' => $tenant->id,
                'package_id' => $package->id,
                'stripe_customer_id' => null,
                'stripe_payment_method_id' => null,
                'billing_cycle' => $billingCycle,
                'status' => 'incomplete',
                'amount' => $pricingDetails['subtotal'],
                'current_period_start' => now(),
                'current_period_end' => $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                'trial_end' => $package->trial_days > 0 ? now()->addDays($package->trial_days) : null,
                'metadata' => json_encode([
                    'package_name' => $package->name,
                    'billing_cycle' => $billingCycle,
                    'addon_count' => count($selectedAddons),
                    'pending_amount' => $pricingDetails['total_amount'],
                    'payment_required' => true,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create addon records
            if (!empty($selectedAddons)) {
                $this->createAddonRecords($subscription, $pricingDetails['addon_details']);
            }

            $tenant->update(['payment_status' => 'pending']);

            DB::commit();

            $reg->update([
                'status' => 'completed_pending_payment',
                'error_message' => 'Payment method required'
            ]);

            Log::info("Pending payment subscription created for tenant {$tenant->id}, registration ID: {$reg->id}");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create pending payment subscription for registration ID {$reg->id}: " . $e->getMessage());
            throw $e;
        }
    }

    private function sendPaymentFailureNotification($tenant, $package, $exception)
    {
        try {
            $owner_user = User::find($tenant->owner_user);
            if (!$owner_user) {
                Log::warning("Owner user not found for payment failure notification: tenant {$tenant->id}");
                return;
            }

            // Only send email if PaymentFailedMail class exists
            if (class_exists('App\Mail\PaymentFailedMail')) {
                Mail::to($owner_user->email)->send(new PaymentFailedMail(
                    $owner_user,
                    $tenant,
                    $package,
                    $exception->getMessage()
                ));

                Log::info("Payment failure notification sent to {$owner_user->email} for tenant {$tenant->id}");
            } else {
                Log::warning("PaymentFailedMail class not found, skipping email notification");
            }

        } catch (\Exception $e) {
            Log::error("Failed to send payment failure notification for tenant {$tenant->id}: " . $e->getMessage());
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
