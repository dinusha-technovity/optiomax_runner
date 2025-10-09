<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Subscription;
use Stripe\Invoice;
use Stripe\Price;
use Stripe\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

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

    public function createSubscriptionWithAddons($tenantId, $packageId, $customerId, $paymentMethodId, $billingCycle, $addonDetails = [])
    {
        try {
            Log::info("Creating Stripe subscription with addons for tenant {$tenantId}");

            // Get package details
            $package = \DB::table('tenant_packages')->where('id', $packageId)->first();
            if (!$package) {
                throw new \Exception("Package not found: {$packageId}");
            }

            // Check if Stripe is properly configured for this package
            $basePrice = $billingCycle === 'yearly' ? $package->stripe_price_id_yearly : $package->stripe_price_id_monthly;
            
            if (!$basePrice) {
                Log::warning("Missing Stripe price ID for package {$package->name}, billing cycle: {$billingCycle}");
                Log::info("Please run 'php artisan stripe:setup-products' to configure Stripe products and prices");
                
                // Return local-only subscription
                return $this->createLocalOnlySubscription($tenantId, $package, $billingCycle, $addonDetails);
            }

            // Check for existing incomplete subscriptions and cancel them
            $this->cancelIncompleteSubscriptions($customerId);

            // Prepare subscription items
            $subscriptionItems = [];

            // Verify price exists in Stripe and add base package item
            try {
                $priceObject = Price::retrieve($basePrice);
                $subscriptionItems[] = [
                    'price' => $basePrice,
                    'quantity' => 1,
                ];
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                if (strpos($e->getMessage(), 'No such price') !== false) {
                    Log::warning("Stripe price {$basePrice} not found. Run 'php artisan stripe:setup-products' to create missing prices.");
                    return $this->createLocalOnlySubscription($tenantId, $package, $billingCycle, $addonDetails);
                }
                throw $e;
            }

            // Add addon items
            foreach ($addonDetails as $addon) {
                $addonRecord = \DB::table('package_addons')->where('id', $addon['addon_id'])->first();
                if ($addonRecord) {
                    $addonPrice = $billingCycle === 'yearly' ? $addonRecord->stripe_price_id_yearly : $addonRecord->stripe_price_id_monthly;
                    if ($addonPrice) {
                        try {
                            $addonPriceObject = Price::retrieve($addonPrice);
                            $subscriptionItems[] = [
                                'price' => $addonPrice,
                                'quantity' => $addon['quantity'],
                            ];
                        } catch (\Exception $e) {
                            Log::warning("Addon price {$addonPrice} not found in Stripe, skipping addon {$addon['name']}");
                        }
                    }
                }
            }

            // Create subscription with proper payment behavior
            $subscription = Subscription::create([
                'customer' => $customerId,
                'items' => $subscriptionItems,
                'default_payment_method' => $paymentMethodId,
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'tenant_id' => $tenantId,
                    'package_id' => $packageId,
                    'billing_cycle' => $billingCycle,
                    'addon_count' => count($addonDetails),
                ],
                'collection_method' => 'charge_automatically',
                'payment_behavior' => 'default_incomplete',
            ]);

            // Handle trial period if applicable - avoid updating subscription unnecessarily
            if ($package->trial_days > 0 && !$subscription->trial_end) {
                try {
                    $subscription = Subscription::update($subscription->id, [
                        'trial_end' => now()->addDays($package->trial_days)->timestamp,
                    ]);
                } catch (\Exception $e) {
                    Log::warning("Failed to set trial period for subscription {$subscription->id}: " . $e->getMessage());
                    // Continue without trial if update fails
                }
            }

            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'local_subscription_id' => null, // Will be set by calling function
                'stripe_payment_intent_id' => $subscription->latest_invoice->payment_intent->id ?? null,
                'stripe_invoice_id' => $subscription->latest_invoice->id ?? null,
                'current_period_start' => date('Y-m-d H:i:s', $subscription->current_period_start),
                'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
                'status' => $subscription->status,
                'client_secret' => $subscription->latest_invoice->payment_intent->client_secret ?? null,
                'metadata' => $subscription->metadata->toArray(),
            ];

        } catch (\Exception $e) {
            Log::error("Stripe subscription creation failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'suggestion' => 'Run "php artisan stripe:setup-products" to configure Stripe products and prices',
            ];
        }
    }

    private function cancelIncompleteSubscriptions($customerId)
    {
        try {
            // Get all incomplete subscriptions for this customer
            $subscriptions = \Stripe\Subscription::all([
                'customer' => $customerId,
                'status' => 'incomplete',
                'limit' => 10,
            ]);

            foreach ($subscriptions->data as $subscription) {
                try {
                    $subscription->cancel();
                    Log::info("Cancelled incomplete subscription: {$subscription->id}");
                } catch (\Exception $e) {
                    Log::warning("Failed to cancel incomplete subscription {$subscription->id}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to check/cancel incomplete subscriptions for customer {$customerId}: " . $e->getMessage());
        }
    }

    // public function retrySubscriptionPayment($subscriptionId)
    // {
    //     try {
    //         $subscription = DB::table('tenant_subscriptions')->find($subscriptionId);
    //         if (!$subscription) {
    //             throw new \Exception('Subscription not found');
    //         }

    //         if ($subscription->stripe_subscription_id && env('STRIPE_SECRET')) {
    //             // Retry Stripe subscription payment
    //             $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_subscription_id);
                
    //             if ($stripeSubscription->latest_invoice) {
    //                 $invoice = \Stripe\Invoice::retrieve($stripeSubscription->latest_invoice);
                    
    //                 if ($invoice->status === 'open') {
    //                     // Attempt to pay the invoice
    //                     $invoice->pay();
                        
    //                     // Update local subscription status
    //                     DB::table('tenant_subscriptions')
    //                         ->where('id', $subscriptionId)
    //                         ->update([
    //                             'status' => 'active',
    //                             'updated_at' => now()
    //                         ]);

    //                     return [
    //                         'success' => true,
    //                         'message' => 'Payment retry successful',
    //                         'amount' => $invoice->amount_paid / 100
    //                     ];
    //                 }
    //             }
    //         }

    //         return [
    //             'success' => false,
    //             'message' => 'No payment to retry or Stripe not configured'
    //         ];

    //     } catch (\Exception $e) {
    //         Log::error('Subscription payment retry failed: ' . $e->getMessage());
    //         return [
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ];
    //     }
    // }

    public function chargeSetupFee($tenantId, $customerId, $paymentMethodId, $amount, $description = 'Setup fee')
    {
        try {
            Log::info("Processing setup fee charge: {$amount} for tenant {$tenantId}");

            $paymentIntent = PaymentIntent::create([
                'amount' => $amount * 100, // Convert to cents
                'currency' => 'usd',
                'customer' => $customerId,
                'payment_method' => $paymentMethodId,
                'description' => $description,
                'confirm' => true,
                'return_url' => config('app.url') . '/payment/return',
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
                'currency' => 'usd',
            ];

        } catch (\Exception $e) {
            Log::error("Setup fee charge failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }

    public function processRecurringPayment($subscriptionId, $tenantId)
    {
        try {
            Log::info("Processing recurring payment for subscription {$subscriptionId}, tenant {$tenantId}");

            // Retrieve the subscription
            $subscription = Subscription::retrieve($subscriptionId);

            // Create invoice and attempt payment
            $invoice = Invoice::create([
                'customer' => $subscription->customer,
                'subscription' => $subscriptionId,
                'auto_advance' => true,
                'metadata' => [
                    'tenant_id' => $tenantId,
                    'type' => 'recurring_payment',
                ],
            ]);

            $invoice->pay();

            return [
                'success' => true,
                'invoice_id' => $invoice->id,
                'payment_intent_id' => $invoice->payment_intent,
                'amount_paid' => $invoice->amount_paid / 100, // Convert from cents
                'status' => $invoice->status,
            ];

        } catch (\Exception $e) {
            Log::error("Recurring payment failed for subscription {$subscriptionId}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'decline_code' => $e->getDeclineCode() ?? null,
            ];
        }
    }

    public function retryFailedPayment($customerId, $paymentMethodId, $amount, $metadata = [])
    {
        try {
            Log::info("Retrying failed payment: {$amount} for customer {$customerId}");

            $paymentIntent = PaymentIntent::create([
                'amount' => $amount * 100,
                'currency' => 'usd',
                'customer' => $customerId,
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'return_url' => config('app.url') . '/payment/return',
                'metadata' => array_merge($metadata, [
                    'type' => 'retry_payment',
                    'retry_timestamp' => now()->toISOString(),
                ]),
            ]);

            return [
                'success' => true,
                'payment_intent_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'amount' => $amount,
            ];

        } catch (\Exception $e) {
            Log::error("Payment retry failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'decline_code' => $e->getDeclineCode() ?? null,
            ];
        }
    }

    public function updateSubscriptionAddons($subscriptionId, $addonChanges)
    {
        try {
            Log::info("Updating subscription addons for subscription {$subscriptionId}");

            $subscription = Subscription::retrieve($subscriptionId);
            $subscriptionItems = [];

            foreach ($addonChanges as $change) {
                if ($change['action'] === 'add') {
                    $subscriptionItems[] = [
                        'price' => $change['price_id'],
                        'quantity' => $change['quantity'],
                    ];
                } elseif ($change['action'] === 'remove' && isset($change['item_id'])) {
                    $subscriptionItems[] = [
                        'id' => $change['item_id'],
                        'deleted' => true,
                    ];
                } elseif ($change['action'] === 'update' && isset($change['item_id'])) {
                    $subscriptionItems[] = [
                        'id' => $change['item_id'],
                        'quantity' => $change['quantity'],
                    ];
                }
            }

            if (!empty($subscriptionItems)) {
                $updatedSubscription = Subscription::update($subscriptionId, [
                    'items' => $subscriptionItems,
                    'proration_behavior' => 'create_prorations',
                ]);

                return [
                    'success' => true,
                    'subscription_id' => $updatedSubscription->id,
                    'status' => $updatedSubscription->status,
                ];
            }

            return [
                'success' => true,
                'message' => 'No changes to apply',
            ];

        } catch (\Exception $e) {
            Log::error("Failed to update subscription addons: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function cancelSubscription($subscriptionId, $immediately = false)
    {
        try {
            if ($immediately) {
                $subscription = Subscription::update($subscriptionId, [
                    'cancel_at_period_end' => false,
                ]);
                $subscription = $subscription->cancel();
            } else {
                $subscription = Subscription::update($subscriptionId, [
                    'cancel_at_period_end' => true,
                ]);
            }

            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
                'canceled_at' => $subscription->canceled_at,
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to cancel subscription {$subscriptionId}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    // public function createSubscription($tenantId, $packageId, $customerId, $paymentMethodId, $billingCycle)
    // {
    //     // Legacy method - redirect to new method
    //     return $this->createSubscriptionWithAddons($tenantId, $packageId, $customerId, $paymentMethodId, $billingCycle, []);
    // }
}