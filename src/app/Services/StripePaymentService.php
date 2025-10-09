<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

class StripePaymentService
{
    private $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(env('STRIPE_SECRET'));
    }

    public function createSubscriptionWithAddons($tenantId, $packageId, $customerId, $paymentMethodId, $billingCycle, $addonDetails = [])
    {
        try {
            Log::info("Creating Stripe subscription with addons for tenant {$tenantId}");

            // First, cancel any incomplete subscriptions for this customer
            $this->cancelIncompleteSubscriptions($customerId);

            // Get package details to determine trial period
            $package = \DB::table('tenant_packages')->where('id', $packageId)->first();
            $trialDays = $package->trial_days ?? 0;

            // Prepare subscription items
            $subscriptionItems = [];
            
            // Add base package item
            $priceId = $billingCycle === 'yearly' ? $package->stripe_price_id_yearly : $package->stripe_price_id_monthly;
            if ($priceId) {
                $subscriptionItems[] = [
                    'price' => $priceId,
                    'quantity' => 1,
                ];
            }

            // Add addon items
            foreach ($addonDetails as $addon) {
                $addonPriceId = $billingCycle === 'yearly' ? 
                    ($addon['stripe_price_id_yearly'] ?? null) : 
                    ($addon['stripe_price_id_monthly'] ?? null);
                
                if ($addonPriceId) {
                    $subscriptionItems[] = [
                        'price' => $addonPriceId,
                        'quantity' => $addon['quantity'] ?? 1,
                    ];
                }
            }

            // Prepare subscription data
            $subscriptionData = [
                'customer' => $customerId,
                'default_payment_method' => $paymentMethodId,
                'collection_method' => 'charge_automatically',
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'tenant_id' => $tenantId,
                    'package_id' => $packageId,
                    'billing_cycle' => $billingCycle,
                    'addon_count' => count($addonDetails),
                ],
            ];

            // Add items if we have them
            if (!empty($subscriptionItems)) {
                $subscriptionData['items'] = $subscriptionItems;
            }

            // Add trial period BEFORE creating subscription
            if ($trialDays > 0) {
                $subscriptionData['trial_period_days'] = $trialDays;
                Log::info("Setting trial period of {$trialDays} days for subscription");
            } else {
                // If no trial, try to confirm payment immediately
                $subscriptionData['payment_behavior'] = 'default_incomplete';
            }

            // Create the subscription
            $subscription = $this->stripe->subscriptions->create($subscriptionData);

            Log::info("Stripe subscription created successfully: {$subscription->id}, status: {$subscription->status}");

            // Handle different subscription statuses
            if ($subscription->status === 'incomplete') {
                Log::warning("Subscription {$subscription->id} is incomplete - attempting to confirm payment");
                
                // Try to confirm the latest invoice
                if ($subscription->latest_invoice && $subscription->latest_invoice->payment_intent) {
                    $paymentIntent = $subscription->latest_invoice->payment_intent;
                    
                    if ($paymentIntent->status === 'requires_confirmation') {
                        try {
                            $confirmedIntent = $this->stripe->paymentIntents->confirm($paymentIntent->id);
                            Log::info("Payment intent confirmed: {$confirmedIntent->id}, status: {$confirmedIntent->status}");
                        } catch (\Exception $e) {
                            Log::warning("Failed to confirm payment intent: " . $e->getMessage());
                        }
                    }
                }
            }

            // Refresh subscription to get latest status
            $subscription = $this->stripe->subscriptions->retrieve($subscription->id);

            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
                'current_period_start' => date('Y-m-d H:i:s', $subscription->current_period_start),
                'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
                'stripe_payment_intent_id' => $subscription->latest_invoice->payment_intent->id ?? null,
                'stripe_invoice_id' => $subscription->latest_invoice->id ?? null,
                'client_secret' => $subscription->latest_invoice->payment_intent->client_secret ?? null,
                'metadata' => [
                    'tenant_id' => $tenantId,
                    'package_id' => $packageId,
                    'billing_cycle' => $billingCycle,
                    'addon_count' => count($addonDetails),
                ]
            ];

        } catch (ApiErrorException $e) {
            Log::error("Stripe API error creating subscription: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ];
        } catch (\Exception $e) {
            Log::error("General error creating Stripe subscription: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function cancelIncompleteSubscriptions($customerId)
    {
        try {
            // Get all incomplete subscriptions for this customer
            $subscriptions = $this->stripe->subscriptions->all([
                'customer' => $customerId,
                'status' => 'incomplete',
                'limit' => 10,
            ]);

            foreach ($subscriptions->data as $subscription) {
                try {
                    $this->stripe->subscriptions->cancel($subscription->id);
                    Log::info("Cancelled incomplete subscription: {$subscription->id}");
                } catch (\Exception $e) {
                    Log::warning("Failed to cancel incomplete subscription {$subscription->id}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::warning("Error checking for incomplete subscriptions: " . $e->getMessage());
        }
    }

    public function chargeSetupFee($tenantId, $customerId, $paymentMethodId, $amount, $description)
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $amount * 100, // Convert to cents
                'currency' => 'usd',
                'customer' => $customerId,
                'payment_method' => $paymentMethodId,
                'confirmation_method' => 'automatic',
                'confirm' => true,
                'description' => $description,
                'metadata' => [
                    'tenant_id' => $tenantId,
                    'type' => 'setup_fee',
                ],
            ]);

            return [
                'success' => true,
                'payment_intent_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'amount' => $amount,
            ];

        } catch (ApiErrorException $e) {
            Log::error("Stripe API error charging setup fee: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}