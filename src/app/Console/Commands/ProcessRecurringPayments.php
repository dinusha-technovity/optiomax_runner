<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TenantSubscription;
use App\Services\StripePaymentService;
use App\Services\PaymentRetryService;
use App\Jobs\ProcessRecurringPaymentJob;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessRecurringPayments extends Command
{
    protected $signature = 'payments:process-recurring {--dry-run : Show what would be processed without actually processing} {--tenant= : Process specific tenant only}';
    protected $description = 'Process recurring payments for active subscriptions due today';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $specificTenant = $this->option('tenant');
        
        $this->info('🔄 Processing Recurring Payments - ' . now()->format('Y-m-d H:i:s'));
        $this->info('=================================================');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No actual payments will be processed');
        }

        try {
            // Get subscriptions due for payment today
            $query = TenantSubscription::with(['tenant', 'package'])
                ->where('status', 'active')
                ->whereDate('current_period_end', '<=', now()->toDateString())
                ->whereNotNull('stripe_subscription_id');

            if ($specificTenant) {
                $query->where('tenant_id', $specificTenant);
            }

            $subscriptionsDue = $query->get();

            $this->info("Found {$subscriptionsDue->count()} subscriptions due for payment");

            if ($subscriptionsDue->count() === 0) {
                $this->info('No subscriptions due for payment today');
                return 0;
            }

            $processed = 0;
            $failed = 0;
            $skipped = 0;

            foreach ($subscriptionsDue as $subscription) {
                $tenant = $subscription->tenant;
                
                if (!$tenant) {
                    $this->error("Tenant not found for subscription {$subscription->id}");
                    $skipped++;
                    continue;
                }

                $this->info("Processing subscription {$subscription->id} for tenant {$tenant->tenant_name} (ID: {$tenant->id})");

                try {
                    if ($dryRun) {
                        $this->line("  [DRY RUN] Would process payment of {$subscription->amount} for {$subscription->billing_cycle} subscription");
                        $processed++;
                        continue;
                    }

                    // Dispatch job for actual processing
                    ProcessRecurringPaymentJob::dispatch($subscription->id)
                        ->onQueue('payments')
                        ->delay(now()->addSeconds($processed * 5)); // Stagger payments

                    $this->info("  ✅ Payment job dispatched for subscription {$subscription->id}");
                    $processed++;

                } catch (\Exception $e) {
                    $this->error("  ❌ Failed to process subscription {$subscription->id}: " . $e->getMessage());
                    Log::error("Recurring payment processing failed for subscription {$subscription->id}: " . $e->getMessage());
                    $failed++;
                }

                // Small delay between processing
                usleep(100000); // 0.1 second
            }

            $this->info("\n📊 Processing Summary:");
            $this->info("  ✅ Processed: {$processed}");
            $this->info("  ❌ Failed: {$failed}");
            $this->info("  ⏭️  Skipped: {$skipped}");

            if (!$dryRun) {
                $this->info("\n💡 Payment jobs have been queued. Monitor the 'payments' queue for progress.");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Fatal error processing recurring payments: " . $e->getMessage());
            Log::error("ProcessRecurringPayments command failed: " . $e->getMessage());
            return 1;
        }
    }
}
