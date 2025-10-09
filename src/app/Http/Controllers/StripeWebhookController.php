<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\tenants;
use App\Models\RegistrationDebug;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (\Exception $e) {
            Log::error('Webhook signature verification failed: ' . $e->getMessage());
            return response('Webhook signature verification failed', 400);
        }

        Log::info("Received Stripe webhook: {$event['type']} for {$event['data']['object']['id']}");

        // Handle the event
        switch ($event['type']) {
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdate($event['data']['object']);
                break;
            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event['data']['object']);
                break;
            case 'invoice.payment_succeeded':
                $this->handlePaymentSucceeded($event['data']['object']);
                break;
            case 'invoice.payment_failed':
                $this->handlePaymentFailed($event['data']['object']);
                break;
            case 'customer.subscription.created':
                $this->handleSubscriptionCreated($event['data']['object']);
                break;
            case 'setup_intent.succeeded':
                $this->handleSetupIntentSucceeded($event['data']['object']);
                break;
            default:
                Log::info('Unhandled webhook event type: ' . $event['type']);
        }

        return response('Webhook handled', 200);
    }

    private function handleSubscriptionUpdate($subscription)
    {
        try {
            // Update local subscription status
            $updated = DB::table('tenant_subscriptions')
                ->where('stripe_subscription_id', $subscription['id'])
                ->update([
                    'status' => $subscription['status'],
                    'current_period_start' => date('Y-m-d H:i:s', $subscription['current_period_start']),
                    'current_period_end' => date('Y-m-d H:i:s', $subscription['current_period_end']),
                    'updated_at' => now(),
                ]);

            if ($updated) {
                // Update tenant payment status if subscription becomes active
                if ($subscription['status'] === 'active') {
                    $this->updateTenantPaymentStatus($subscription['id'], 'active');
                    $this->completeRegistrationIfPending($subscription['id']);
                }
                
                Log::info("Updated subscription {$subscription['id']} status to {$subscription['status']}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to handle subscription update: " . $e->getMessage());
        }
    }

    private function handlePaymentSucceeded($invoice)
    {
        try {
            if (!isset($invoice['subscription'])) {
                return; // Not a subscription payment
            }

            // Update subscription status
            DB::table('tenant_subscriptions')
                ->where('stripe_subscription_id', $invoice['subscription'])
                ->update([
                    'status' => 'active',
                    'current_period_start' => date('Y-m-d H:i:s', $invoice['period_start']),
                    'current_period_end' => date('Y-m-d H:i:s', $invoice['period_end']),
                    'updated_at' => now(),
                ]);

            // Update tenant payment status
            $this->updateTenantPaymentStatus($invoice['subscription'], 'active');
            
            // Complete any pending registrations
            $this->completeRegistrationIfPending($invoice['subscription']);

            // Record payment transaction
            $this->recordPaymentTransaction($invoice);

            Log::info("Payment succeeded for subscription {$invoice['subscription']}");
        } catch (\Exception $e) {
            Log::error("Failed to handle payment success: " . $e->getMessage());
        }
    }

    private function handlePaymentFailed($invoice)
    {
        try {
            if (!isset($invoice['subscription'])) {
                return;
            }

            // Update subscription status
            DB::table('tenant_subscriptions')
                ->where('stripe_subscription_id', $invoice['subscription'])
                ->update([
                    'status' => 'past_due',
                    'updated_at' => now(),
                ]);

            // Update tenant payment status
            $this->updateTenantPaymentStatus($invoice['subscription'], 'past_due');

            Log::warning("Payment failed for subscription {$invoice['subscription']}");
        } catch (\Exception $e) {
            Log::error("Failed to handle payment failure: " . $e->getMessage());
        }
    }

    private function updateTenantPaymentStatus($stripeSubscriptionId, $status)
    {
        try {
            DB::table('tenants')
                ->where('stripe_subscription_id', $stripeSubscriptionId)
                ->update([
                    'payment_status' => $status,
                    'updated_at' => now(),
                ]);
        } catch (\Exception $e) {
            Log::error("Failed to update tenant payment status: " . $e->getMessage());
        }
    }

    private function completeRegistrationIfPending($stripeSubscriptionId)
    {
        try {
            // Find any pending registrations for this subscription
            $tenant = DB::table('tenants')
                ->where('stripe_subscription_id', $stripeSubscriptionId)
                ->first();

            if ($tenant) {
                $pendingRegistration = RegistrationDebug::where('status', 'payment_processing')
                    ->whereJsonContains('validated_user->tenant_id', $tenant->id)
                    ->first();

                if ($pendingRegistration) {
                    $pendingRegistration->update([
                        'status' => 'completed_with_payment',
                        'error_message' => null
                    ]);
                    
                    Log::info("Completed pending registration {$pendingRegistration->id} via webhook");
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to complete pending registration: " . $e->getMessage());
        }
    }

    private function recordPaymentTransaction($invoice)
    {
        try {
            $tenant = DB::table('tenants')
                ->where('stripe_subscription_id', $invoice['subscription'])
                ->first();

            if ($tenant) {
                DB::table('payment_transactions')->insert([
                    'tenant_id' => $tenant->id,
                    'stripe_payment_intent_id' => $invoice['payment_intent'] ?? null,
                    'stripe_invoice_id' => $invoice['id'],
                    'type' => 'subscription',
                    'amount' => $invoice['amount_paid'] / 100, // Convert from cents
                    'currency' => $invoice['currency'],
                    'status' => 'succeeded',
                    'description' => 'Webhook-recorded subscription payment',
                    'stripe_response' => json_encode($invoice),
                    'processed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to record payment transaction: " . $e->getMessage());
        }
    }
}