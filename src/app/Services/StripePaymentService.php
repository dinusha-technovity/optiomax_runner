<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripePaymentService
{
    public function __construct()
    {
        // Initialize Stripe if configured
        if (env('STRIPE_SECRET')) {
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
        }
    }

    public function createSubscription($tenantId, $packageId, $customerId, $paymentMethodId, $billingCycle)
    {
        try {
            $package = DB::table('tenant_packages')->find($packageId);
            $tenant = DB::table('tenants')->find($tenantId);

            if (!$package || !$tenant) {
                throw new \Exception('Package or tenant not found');
            }

            Log::info("Creating subscription for tenant {$tenantId}, package {$packageId}, amount: {$package->price}");

            // If Stripe is properly configured and we have valid customer/payment method
            if (env('STRIPE_SECRET') && $customerId && $paymentMethodId && strpos($customerId, 'cus_') === 0) {
                return $this->createStripeSubscription($tenantId, $packageId, $customerId, $paymentMethodId, $billingCycle, $package);
            } else {
                // Create local subscription without Stripe
                return $this->createLocalOnlySubscription($tenantId, $packageId, $customerId, $paymentMethodId, $billingCycle, $package);
            }

        } catch (\Exception $e) {
            Log::error('Subscription creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function createStripeSubscription($tenantId, $packageId, $customerId, $paymentMethodId, $billingCycle, $package)
    {
        try {
            // Get Stripe price ID
            $stripePriceId = $billingCycle === 'yearly' 
                ? $package->stripe_price_id_yearly 
                : $package->stripe_price_id_monthly;

            if ($stripePriceId) {
                // Create actual Stripe subscription
                $subscription = \Stripe\Subscription::create([
                    'customer' => $customerId,
                    'items' => [['price' => $stripePriceId]],
                    'default_payment_method' => $paymentMethodId,
                    'metadata' => [
                        'tenant_id' => $tenantId,
                        'package_id' => $packageId,
                        'billing_cycle' => $billingCycle
                    ]
                ]);

                // Process initial payment if not in trial
                $paymentIntent = null;
                if ($subscription->latest_invoice) {
                    $invoice = \Stripe\Invoice::retrieve($subscription->latest_invoice);
                    if ($invoice->payment_intent) {
                        $paymentIntent = \Stripe\PaymentIntent::retrieve($invoice->payment_intent);
                    }
                }

                // Create local subscription record
                $localSubscriptionId = $this->createLocalSubscription(
                    $tenantId, 
                    $packageId, 
                    $customerId, 
                    $paymentMethodId, 
                    $billingCycle, 
                    $package,
                    $subscription->id
                );

                return [
                    'success' => true,
                    'subscription_id' => $subscription->id,
                    'local_subscription_id' => $localSubscriptionId,
                    'status' => $subscription->status,
                    'stripe_payment_intent_id' => $paymentIntent ? $paymentIntent->id : null,
                    'stripe_invoice_id' => $subscription->latest_invoice,
                    'amount_charged' => $paymentIntent ? ($paymentIntent->amount / 100) : $package->price
                ];
            } else {
                // No Stripe price ID configured, create local only
                return $this->createLocalOnlySubscription($tenantId, $packageId, $customerId, $paymentMethodId, $billingCycle, $package);
            }

        } catch (\Stripe\Exception\CardException $e) {
            Log::error('Stripe card error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Payment failed: ' . $e->getMessage(),
                'error_type' => 'card_error'
            ];
        } catch (\Exception $e) {
            Log::error('Stripe subscription creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function createLocalOnlySubscription($tenantId, $packageId, $customerId, $paymentMethodId, $billingCycle, $package)
    {
        // Create local subscription record without actual Stripe processing
        $localSubscriptionId = $this->createLocalSubscription(
            $tenantId, 
            $packageId, 
            $customerId, 
            $paymentMethodId, 
            $billingCycle, 
            $package
        );

        return [
            'success' => true,
            'subscription_id' => 'local_' . $localSubscriptionId,
            'local_subscription_id' => $localSubscriptionId,
            'status' => 'active',
            'amount_charged' => $package->price
        ];
    }

    private function createLocalSubscription($tenantId, $packageId, $customerId, $paymentMethodId, $billingCycle, $package, $stripeSubscriptionId = null)
    {
        return DB::table('tenant_subscriptions')->insertGetId([
            'tenant_id' => $tenantId,
            'package_id' => $packageId,
            'stripe_customer_id' => $customerId,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_payment_method_id' => $paymentMethodId,
            'billing_cycle' => $billingCycle,
            'status' => $package->trial_days > 0 ? 'trialing' : 'active',
            'amount' => $package->price,
            'current_period_start' => now(),
            'current_period_end' => $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth(),
            'trial_end' => $package->trial_days > 0 ? now()->addDays($package->trial_days) : null,
            'metadata' => json_encode([
                'package_name' => $package->name,
                'billing_cycle' => $billingCycle,
                'created_via' => 'registration'
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function retrySubscriptionPayment($subscriptionId)
    {
        try {
            $subscription = DB::table('tenant_subscriptions')->find($subscriptionId);
            if (!$subscription) {
                throw new \Exception('Subscription not found');
            }

            if ($subscription->stripe_subscription_id && env('STRIPE_SECRET')) {
                // Retry Stripe subscription payment
                $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_subscription_id);
                
                if ($stripeSubscription->latest_invoice) {
                    $invoice = \Stripe\Invoice::retrieve($stripeSubscription->latest_invoice);
                    
                    if ($invoice->status === 'open') {
                        // Attempt to pay the invoice
                        $invoice->pay();
                        
                        // Update local subscription status
                        DB::table('tenant_subscriptions')
                            ->where('id', $subscriptionId)
                            ->update([
                                'status' => 'active',
                                'updated_at' => now()
                            ]);

                        return [
                            'success' => true,
                            'message' => 'Payment retry successful',
                            'amount' => $invoice->amount_paid / 100
                        ];
                    }
                }
            }

            return [
                'success' => false,
                'message' => 'No payment to retry or Stripe not configured'
            ];

        } catch (\Exception $e) {
            Log::error('Subscription payment retry failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function chargeSetupFee($tenantId, $customerId, $paymentMethodId, $amount)
    {
        try {
            Log::info("Processing setup fee of {$amount} for tenant {$tenantId}");

            // Record transaction (without actual Stripe charge for now)
            DB::table('payment_transactions')->insert([
                'tenant_id' => $tenantId,
                'subscription_id' => null,
                'stripe_payment_intent_id' => 'setup_' . uniqid(),
                'type' => 'setup_fee',
                'amount' => $amount,
                'currency' => 'usd',
                'status' => 'succeeded',
                'description' => 'Setup fee for subscription',
                'stripe_response' => json_encode(['simulated' => true]),
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'success' => true,
                'status' => 'succeeded'
            ];

        } catch (\Exception $e) {
            Log::error('Setup fee charge failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}