<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stripe\Stripe;
use Stripe\Product;
use Stripe\Price;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SetupStripeProducts extends Command
{
    protected $signature = 'stripe:setup-products {--update : Update existing products} {--test-keys : Show current Stripe configuration}';
    protected $description = 'Create Stripe products and prices for all tenant packages';

    public function handle()
    {
        // Show configuration if requested
        if ($this->option('test-keys')) {
            $this->showStripeConfiguration();
            return 0;
        }

        // Check for Stripe secret key in multiple possible config locations
        $stripeSecret = $this->getStripeSecret();
        
        if (!$stripeSecret) {
            $this->error('❌ Stripe secret key not found in configuration');
            $this->info('');
            $this->info('Please ensure one of the following is set:');
            $this->info('1. STRIPE_SECRET in your Docker environment file');
            $this->info('2. services.stripe.secret in config/services.php');
            $this->info('3. STRIPE_SECRET environment variable');
            $this->info('');
            $this->info('Current configuration check:');
            $this->showStripeConfiguration();
            $this->info('');
            $this->warn('💡 For Docker setups:');
            $this->info('1. Make sure your Docker env file has STRIPE_SECRET');
            $this->info('2. Restart your containers after adding the key');
            $this->info('3. Run: docker-compose down && docker-compose up -d');
            return 1;
        }

        try {
            Stripe::setApiKey($stripeSecret);
            $this->info("✅ Stripe API key configured successfully");
            
            // Test the API key with a simple call
            \Stripe\Account::retrieve();
            $this->info("✅ Stripe API connection successful");
            
        } catch (\Exception $e) {
            $this->error("❌ Stripe API connection failed: " . $e->getMessage());
            $this->info('');
            $this->warn('Common issues:');
            $this->info('1. Invalid Stripe secret key');
            $this->info('2. Network connectivity issues');
            $this->info('3. Stripe account restrictions');
            return 1;
        }
        
        $this->info('🚀 Setting up Stripe products and prices...');
        
        // Get all active packages
        $packages = DB::table('tenant_packages')
            ->where('isactive', true)
            ->whereNull('deleted_at')
            ->get();

        if ($packages->isEmpty()) {
            $this->warn('⚠️  No active packages found. Run the package seeder first:');
            $this->info('php artisan db:seed --class=TenantPackagesSeeder');
            return 1;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($packages as $package) {
            $this->info("\n📦 Processing package: {$package->name}");
            
            try {
                // Create or update Stripe product
                $product = $this->createOrUpdateStripeProduct($package);
                
                // Create or update prices
                $monthlyPrice = null;
                $yearlyPrice = null;
                
                if ($package->base_price_monthly > 0) {
                    $monthlyPrice = $this->createOrUpdateStripePrice(
                        $product->id,
                        $package,
                        'monthly',
                        $package->base_price_monthly
                    );
                }
                
                if ($package->base_price_yearly > 0) {
                    $yearlyPrice = $this->createOrUpdateStripePrice(
                        $product->id,
                        $package,
                        'yearly',
                        $package->base_price_yearly
                    );
                }
                
                // Update package with Stripe IDs
                $updateData = ['stripe_product_id' => $product->id];
                if ($monthlyPrice) $updateData['stripe_price_id_monthly'] = $monthlyPrice->id;
                if ($yearlyPrice) $updateData['stripe_price_id_yearly'] = $yearlyPrice->id;
                
                DB::table('tenant_packages')
                    ->where('id', $package->id)
                    ->update($updateData);
                
                $this->info("  ✅ Product: {$product->id}");
                if ($monthlyPrice) $this->info("  💰 Monthly Price: {$monthlyPrice->id}");
                if ($yearlyPrice) $this->info("  💰 Yearly Price: {$yearlyPrice->id}");
                
                $created++;
                
            } catch (\Exception $e) {
                $this->error("  ❌ Failed to process {$package->name}: " . $e->getMessage());
                Log::error("Stripe setup failed for package {$package->id}: " . $e->getMessage());
                continue;
            }
        }

        // Also setup addons
        $this->setupAddons();

        $this->info("\n🎉 Stripe setup completed!");
        $this->info("  ✅ Created/Updated: {$created} packages");
        $this->info("  ⏭️  Skipped: {$skipped} packages");
        
        // Show next steps
        $this->info("\n📋 Next Steps:");
        $this->info("1. Run: php artisan stripe:check");
        $this->info("2. Test your payment flow");
        $this->info("3. Check your Stripe dashboard for created products");
        
        return 0;
    }

    private function getStripeSecret()
    {
        // Try multiple configuration sources in order of preference
        $sources = [
            'env_stripe_secret' => env('STRIPE_SECRET'),
            'config_stripe_secret' => config('services.stripe.secret'),
            'env_stripe_key_as_secret' => env('STRIPE_KEY'), // Sometimes people mix these up
            'direct_env_check' => $_ENV['STRIPE_SECRET'] ?? null, // Direct environment check
        ];

        foreach ($sources as $source => $value) {
            if ($value && str_starts_with($value, 'sk_')) {
                $this->info("✅ Found Stripe secret key from: {$source}");
                return $value;
            }
        }

        return null;
    }

    private function showStripeConfiguration()
    {
        $this->info('🔍 Current Stripe Configuration:');
        $this->info('================================');
        
        // Check environment variables directly
        $envStripeSecret = $_ENV['STRIPE_SECRET'] ?? null;
        $envStripeKey = $_ENV['STRIPE_KEY'] ?? null;
        
        $configs = [
            'env(STRIPE_SECRET)' => env('STRIPE_SECRET'),
            'env(STRIPE_KEY)' => env('STRIPE_KEY'),
            'config(services.stripe.secret)' => config('services.stripe.secret'),
            'config(services.stripe.key)' => config('services.stripe.key'),
            '$_ENV[STRIPE_SECRET]' => $envStripeSecret,
            '$_ENV[STRIPE_KEY]' => $envStripeKey,
        ];

        foreach ($configs as $source => $value) {
            if ($value) {
                $masked = $this->maskKey($value);
                $isSecret = str_starts_with($value, 'sk_');
                $isPublic = str_starts_with($value, 'pk_');
                
                if ($isSecret) {
                    $icon = '✅ SECRET';
                } elseif ($isPublic) {
                    $icon = '🔑 PUBLIC';
                } else {
                    $icon = '❌ INVALID';
                }
                
                $this->info("  {$icon} {$source}: {$masked}");
            } else {
                $this->info("  ❌ {$source}: Not set");
            }
        }
        
        // Docker specific checks
        $this->info('');
        $this->info('🐳 Docker Environment Checks:');
        $this->info('================================');
        
        // Check if running in Docker
        $isDocker = file_exists('/.dockerenv') || (getenv('container') !== false);
        $this->info('Running in Docker: ' . ($isDocker ? 'Yes' : 'No'));
        
        // Check Docker env file
        $dockerEnvPath = '/home/chamod-randeni/Documents/optiomax project/optiomax_runner/docker-for-Laravel/envs/app.env';
        if (file_exists($dockerEnvPath)) {
            $this->info('Docker env file exists: ✅');
            $envContent = file_get_contents($dockerEnvPath);
            if (strpos($envContent, 'STRIPE_SECRET=') !== false) {
                $this->info('STRIPE_SECRET found in Docker env: ✅');
            } else {
                $this->info('STRIPE_SECRET found in Docker env: ❌');
            }
        } else {
            $this->info('Docker env file exists: ❌');
        }
    }

    private function maskKey($key)
    {
        if (strlen($key) < 10) {
            return '***hidden***';
        }
        return substr($key, 0, 7) . '***' . substr($key, -4);
    }

    private function setupAddons()
    {
        $this->info("\n🔧 Setting up Package Addons...");
        
        $addons = DB::table('package_addons')
            ->where('isactive', true)
            ->get();

        if ($addons->isEmpty()) {
            $this->warn('⚠️  No active addons found. Run the addons seeder:');
            $this->info('php artisan db:seed --class=PackageAddonsSeeder');
            return;
        }

        foreach ($addons as $addon) {
            $this->info("\n🧩 Processing addon: {$addon->name}");
            
            try {
                // Create addon product
                $product = $this->createAddonProduct($addon);
                
                // Create addon prices
                $monthlyPrice = null;
                $yearlyPrice = null;
                
                if ($addon->price_monthly > 0) {
                    $monthlyPrice = $this->createAddonPrice($product->id, $addon, 'monthly', $addon->price_monthly);
                }
                
                if ($addon->price_yearly > 0) {
                    $yearlyPrice = $this->createAddonPrice($product->id, $addon, 'yearly', $addon->price_yearly);
                }
                
                // Update addon with Stripe IDs
                $updateData = ['stripe_product_id' => $product->id];
                if ($monthlyPrice) $updateData['stripe_price_id_monthly'] = $monthlyPrice->id;
                if ($yearlyPrice) $updateData['stripe_price_id_yearly'] = $yearlyPrice->id;
                
                DB::table('package_addons')
                    ->where('id', $addon->id)
                    ->update($updateData);
                
                $this->info("  ✅ Addon Product: {$product->id}");
                if ($monthlyPrice) $this->info("  💰 Monthly Price: {$monthlyPrice->id}");
                if ($yearlyPrice) $this->info("  💰 Yearly Price: {$yearlyPrice->id}");
                
            } catch (\Exception $e) {
                $this->error("  ❌ Failed to process addon {$addon->name}: " . $e->getMessage());
                Log::error("Stripe addon setup failed for {$addon->id}: " . $e->getMessage());
            }
        }
    }

    private function createOrUpdateStripeProduct($package)
    {
        $productData = [
            'name' => $package->name . ' Plan',
            'description' => $package->description,
            'metadata' => [
                'package_id' => $package->id,
                'package_slug' => $package->slug,
                'billing_type' => $package->billing_type,
            ],
            'tax_code' => 'txcd_10000000', // Software as a Service
        ];

        // Check if product already exists
        if ($package->stripe_product_id) {
            try {
                $product = Product::retrieve($package->stripe_product_id);
                if ($this->option('update')) {
                    $product = Product::update($package->stripe_product_id, $productData);
                    $this->info("  🔄 Updated existing product");
                }
                return $product;
            } catch (\Exception $e) {
                $this->warn("  ⚠️  Existing product not found, creating new one");
            }
        }

        // Create new product
        $product = Product::create($productData);
        $this->info("  ✨ Created new product");
        
        return $product;
    }

    private function createOrUpdateStripePrice($productId, $package, $interval, $amount)
    {
        if ($amount <= 0) {
            return null; // Don't create prices for free packages
        }

        $priceData = [
            'product' => $productId,
            'unit_amount' => $amount * 100, // Convert to cents
            'currency' => 'usd',
            'recurring' => [
                'interval' => $interval === 'yearly' ? 'year' : 'month',
                'interval_count' => 1,
            ],
            'metadata' => [
                'package_id' => $package->id,
                'package_slug' => $package->slug,
                'billing_type' => $interval,
            ],
            'tax_behavior' => 'exclusive',
        ];

        // Check if price already exists
        $existingPriceId = $interval === 'monthly' 
            ? $package->stripe_price_id_monthly 
            : $package->stripe_price_id_yearly;

        if ($existingPriceId) {
            try {
                $price = Price::retrieve($existingPriceId);
                $this->info("  🔍 Using existing {$interval} price");
                return $price;
            } catch (\Exception $e) {
                $this->warn("  ⚠️  Existing price not found, creating new one");
            }
        }

        // Create new price
        $price = Price::create($priceData);
        $this->info("  💲 Created {$interval} price: $" . number_format($amount, 2));
        
        return $price;
    }

    private function createAddonProduct($addon)
    {
        $productData = [
            'name' => $addon->name,
            'description' => $addon->description,
            'metadata' => [
                'addon_id' => $addon->id,
                'addon_slug' => $addon->slug,
                'addon_type' => $addon->addon_type,
                'target_feature' => $addon->target_feature,
            ],
        ];

        if ($addon->stripe_product_id) {
            try {
                $product = Product::retrieve($addon->stripe_product_id);
                if ($this->option('update')) {
                    $product = Product::update($addon->stripe_product_id, $productData);
                    $this->info("  🔄 Updated existing addon product");
                }
                return $product;
            } catch (\Exception $e) {
                $this->warn("  ⚠️  Existing addon product not found, creating new one");
            }
        }

        $product = Product::create($productData);
        $this->info("  ✨ Created new addon product");
        
        return $product;
    }

    private function createAddonPrice($productId, $addon, $interval, $amount)
    {
        if ($amount <= 0) {
            return null;
        }

        $priceData = [
            'product' => $productId,
            'unit_amount' => $amount * 100,
            'currency' => 'usd',
            'recurring' => [
                'interval' => $interval === 'yearly' ? 'year' : 'month',
                'interval_count' => 1,
            ],
            'metadata' => [
                'addon_id' => $addon->id,
                'addon_slug' => $addon->slug,
                'billing_type' => $interval,
            ],
        ];

        $existingPriceId = $interval === 'monthly' 
            ? $addon->stripe_price_id_monthly 
            : $addon->stripe_price_id_yearly;

        if ($existingPriceId) {
            try {
                $price = Price::retrieve($existingPriceId);
                $this->info("  🔍 Using existing {$interval} addon price");
                return $price;
            } catch (\Exception $e) {
                $this->warn("  ⚠️  Existing addon price not found, creating new one");
            }
        }

        $price = Price::create($priceData);
        $this->info("  💲 Created {$interval} addon price: $" . number_format($amount, 2));
        
        return $price;
    }
}
