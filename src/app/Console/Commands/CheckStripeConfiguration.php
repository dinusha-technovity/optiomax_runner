<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stripe\Stripe;
use Stripe\Product;
use Stripe\Price;
use Illuminate\Support\Facades\DB;

class CheckStripeConfiguration extends Command
{
    protected $signature = 'stripe:check';
    protected $description = 'Check Stripe configuration for all packages';

    public function handle()
    {
        if (!config('services.stripe.secret')) {
            $this->error('❌ Stripe secret key not configured in .env file');
            $this->info('Add STRIPE_SECRET=sk_test_... to your .env file');
            return 1;
        }

        Stripe::setApiKey(config('services.stripe.secret'));
        
        $this->info('🔍 Checking Stripe configuration...');
        
        $packages = DB::table('tenant_packages')
            ->where('isactive', true)
            ->whereNull('deleted_at')
            ->get();

        $issues = 0;
        
        foreach ($packages as $package) {
            $this->info("\n📦 {$package->name}:");
            
            // Check product
            if ($package->stripe_product_id) {
                try {
                    Product::retrieve($package->stripe_product_id);
                    $this->info("  ✅ Product: {$package->stripe_product_id}");
                } catch (\Exception $e) {
                    $this->error("  ❌ Product not found: {$package->stripe_product_id}");
                    $issues++;
                }
            } else {
                $this->warn("  ⚠️  No Stripe product ID configured");
                $issues++;
            }
            
            // Check monthly price
            if ($package->base_price_monthly > 0) {
                if ($package->stripe_price_id_monthly) {
                    try {
                        Price::retrieve($package->stripe_price_id_monthly);
                        $this->info("  ✅ Monthly price: {$package->stripe_price_id_monthly}");
                    } catch (\Exception $e) {
                        $this->error("  ❌ Monthly price not found: {$package->stripe_price_id_monthly}");
                        $issues++;
                    }
                } else {
                    $this->warn("  ⚠️  No monthly price ID configured");
                    $issues++;
                }
            }
            
            // Check yearly price
            if ($package->base_price_yearly > 0) {
                if ($package->stripe_price_id_yearly) {
                    try {
                        Price::retrieve($package->stripe_price_id_yearly);
                        $this->info("  ✅ Yearly price: {$package->stripe_price_id_yearly}");
                    } catch (\Exception $e) {
                        $this->error("  ❌ Yearly price not found: {$package->stripe_price_id_yearly}");
                        $issues++;
                    }
                } else {
                    $this->warn("  ⚠️  No yearly price ID configured");
                    $issues++;
                }
            }
        }
        
        if ($issues > 0) {
            $this->error("\n❌ Found {$issues} configuration issues");
            $this->info("Run 'php artisan stripe:setup-products' to fix these issues");
            return 1;
        } else {
            $this->info("\n🎉 All Stripe configurations are valid!");
            return 0;
        }
    }
}
