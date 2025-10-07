<?php

namespace App\Services;

use App\Models\TenantPackage;
use App\Models\PackageAddon;
use App\Models\PackageDiscount;
use App\Models\PackageLimitOverride;
use App\Models\TenantSubscription;
use App\Models\tenants;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PackageManagementService
{
    public function getTenantEffectiveLimits($tenantId, $packageId = null)
    {
        try {
            $tenant = tenants::find($tenantId);
            if (!$tenant) {
                throw new \Exception('Tenant not found');
            }

            // Get active subscription if package ID not provided
            if (!$packageId) {
                $subscription = TenantSubscription::where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->with('package')
                    ->first();
                
                if (!$subscription) {
                    throw new \Exception('No active subscription found');
                }
                
                $package = $subscription->package;
            } else {
                $package = TenantPackage::find($packageId);
                if (!$package) {
                    throw new \Exception('Package not found');
                }
            }

            // Start with base package limits
            $limits = [
                'credits' => $package->credits,
                'workflows' => $package->workflows,
                'users' => $package->users,
                'storage' => $package->max_storage_gb,
            ];

            // Apply limit overrides
            $overrides = PackageLimitOverride::where('tenant_id', $tenantId)
                ->where('package_id', $package->id)
                ->where(function($query) {
                    $query->where('is_permanent', true)
                          ->orWhere(function($q) {
                              $q->where('effective_from', '<=', now())
                                ->where(function($subQ) {
                                    $subQ->whereNull('effective_until')
                                         ->orWhere('effective_until', '>', now());
                                });
                          });
                })
                ->get();

            foreach ($overrides as $override) {
                if ($override->isActive()) {
                    $limits[$override->limit_type] = $override->override_value;
                }
            }

            // Apply active addons if subscription exists
            if (isset($subscription)) {
                $activeAddons = $subscription->addons()->where('status', 'active')->with('addon')->get();
                
                foreach ($activeAddons as $subscriptionAddon) {
                    $addon = $subscriptionAddon->addon;
                    $additionalAmount = $addon->quantity * $subscriptionAddon->quantity;
                    
                    if (isset($limits[$addon->type])) {
                        $limits[$addon->type] += $additionalAmount;
                    }
                }
            }

            return [
                'success' => true,
                'limits' => $limits,
                'package' => $package,
                'overrides_applied' => $overrides->count(),
                'addons_applied' => isset($activeAddons) ? $activeAddons->count() : 0
            ];

        } catch (\Exception $e) {
            Log::error("Failed to get tenant effective limits: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'limits' => []
            ];
        }
    }

    public function applyLimitOverride($tenantId, $packageId, $limitType, $newValue, $reason = null, $duration = null, $createdBy = null)
    {
        try {
            DB::beginTransaction();

            $package = TenantPackage::find($packageId);
            if (!$package) {
                throw new \Exception('Package not found');
            }

            $originalValue = $package->{$limitType};
            if ($originalValue === null) {
                throw new \Exception("Limit type '{$limitType}' not found in package");
            }

            $effectiveUntil = null;
            $isPermanent = false;

            if ($duration) {
                $effectiveUntil = now()->addDays($duration);
            } else {
                $isPermanent = true;
            }

            $override = PackageLimitOverride::create([
                'tenant_id' => $tenantId,
                'package_id' => $packageId,
                'limit_type' => $limitType,
                'original_value' => $originalValue,
                'override_value' => $newValue,
                'reason' => $reason,
                'effective_from' => now(),
                'effective_until' => $effectiveUntil,
                'is_permanent' => $isPermanent,
                'created_by' => $createdBy
            ]);

            DB::commit();

            Log::info("Limit override applied for tenant {$tenantId}: {$limitType} changed from {$originalValue} to {$newValue}");

            return [
                'success' => true,
                'override' => $override,
                'message' => 'Limit override applied successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to apply limit override: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function validateDiscountCode($code, $packageId, $packageType, $billingCycle, $customerId = null)
    {
        try {
            $discount = PackageDiscount::where('code', $code)
                ->where('isactive', true)
                ->first();

            if (!$discount) {
                return [
                    'success' => false,
                    'message' => 'Invalid discount code'
                ];
            }

            if (!$discount->isValidForUse()) {
                return [
                    'success' => false,
                    'message' => 'Discount code has expired or reached usage limit'
                ];
            }

            if (!$discount->isApplicableToPackage($packageId, $packageType)) {
                return [
                    'success' => false,
                    'message' => 'Discount code is not applicable to this package'
                ];
            }

            // Check billing cycle compatibility
            if ($discount->billing_cycles !== 'both' && $discount->billing_cycles !== $billingCycle) {
                return [
                    'success' => false,
                    'message' => 'Discount code is not applicable to this billing cycle'
                ];
            }

            // Check customer usage limit
            if ($customerId && $discount->usage_limit_per_customer > 0) {
                $customerUsage = SubscriptionDiscount::whereHas('subscription', function($query) use ($customerId) {
                    $query->where('stripe_customer_id', $customerId);
                })->where('discount_id', $discount->id)->count();

                if ($customerUsage >= $discount->usage_limit_per_customer) {
                    return [
                        'success' => false,
                        'message' => 'You have already used this discount code'
                    ];
                }
            }

            return [
                'success' => true,
                'discount' => $discount,
                'message' => 'Discount code is valid'
            ];

        } catch (\Exception $e) {
            Log::error("Failed to validate discount code: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error validating discount code'
            ];
        }
    }

    public function getAvailableAddons($packageId)
    {
        try {
            $package = TenantPackage::find($packageId);
            if (!$package) {
                throw new \Exception('Package not found');
            }

            $query = PackageAddon::where('isactive', true);

            // If package has specific addon restrictions
            if ($package->available_addons) {
                $query->whereIn('id', $package->available_addons);
            } else {
                // Check if addon is applicable to this package
                $query->where(function($q) use ($packageId) {
                    $q->whereNull('applicable_packages')
                      ->orWhereJsonContains('applicable_packages', $packageId);
                });
            }

            $addons = $query->orderBy('sort_order')->get();

            return [
                'success' => true,
                'addons' => $addons,
                'package' => $package
            ];

        } catch (\Exception $e) {
            Log::error("Failed to get available addons: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'addons' => []
            ];
        }
    }

    public function calculatePackageTotal($packageId, $billingCycle, $addons = [], $discountCode = null, $packageType = null)
    {
        try {
            $package = TenantPackage::find($packageId);
            if (!$package) {
                throw new \Exception('Package not found');
            }

            $basePrice = $billingCycle === 'yearly' ? 
                ($package->discount_price ?? $package->price) : 
                $package->price;

            $addonTotal = 0;
            $addonDetails = [];

            // Calculate addons
            foreach ($addons as $addonData) {
                $addon = PackageAddon::find($addonData['addon_id']);
                if ($addon && $addon->isApplicableToPackage($packageId)) {
                    $quantity = $addonData['quantity'] ?? 1;
                    $price = $addon->getPriceForBillingCycle($billingCycle);
                    $lineTotal = $price * $quantity;
                    
                    $addonTotal += $lineTotal;
                    $addonDetails[] = [
                        'addon' => $addon,
                        'quantity' => $quantity,
                        'unit_price' => $price,
                        'total_price' => $lineTotal
                    ];
                }
            }

            $subtotal = $basePrice + $addonTotal;
            $discountAmount = 0;
            $discountDetails = null;

            // Apply discount if provided
            if ($discountCode) {
                $discountValidation = $this->validateDiscountCode($discountCode, $packageId, $packageType, $billingCycle);
                if ($discountValidation['success']) {
                    $discount = $discountValidation['discount'];
                    
                    if (!$discount->minimum_amount || $subtotal >= $discount->minimum_amount) {
                        $discountAmount = $discount->calculateDiscount($subtotal);
                        $discountDetails = [
                            'discount' => $discount,
                            'amount' => $discountAmount
                        ];
                    }
                }
            }

            $setupFee = $package->setup_fee ?? 0;
            $total = $subtotal - $discountAmount + $setupFee;

            return [
                'success' => true,
                'calculation' => [
                    'package' => $package,
                    'base_price' => $basePrice,
                    'addon_total' => $addonTotal,
                    'addon_details' => $addonDetails,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'discount_details' => $discountDetails,
                    'setup_fee' => $setupFee,
                    'total' => $total,
                    'billing_cycle' => $billingCycle
                ]
            ];

        } catch (\Exception $e) {
            Log::error("Failed to calculate package total: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
