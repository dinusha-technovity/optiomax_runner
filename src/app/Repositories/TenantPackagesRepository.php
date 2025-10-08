<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TenantPackagesRepository
{
    public function getAllTenantPackages(?int $tenantPackagesId = null, ?string $packageType = null, ?string $billingCycle = null, ?string $region = null)
    {
        DB::beginTransaction();

        try {
            // Convert billing cycle format if needed
            $dbBillingCycle = null;
            if ($billingCycle) {
                $dbBillingCycle = $billingCycle === 'Monthly' ? 'monthly' : 'yearly';
            }

            // Call the enhanced PostgreSQL function with new parameters
            $result = DB::select(
                "SELECT * FROM get_tenant_packages_list(?, ?, ?, ?, ?, ?)",
                [$tenantPackagesId, $packageType, $dbBillingCycle, $region, true, true]
            );

            if (!empty($result)) {
                $response = collect($result)->map(function ($item) {
                    $itemArray = (array) $item;
                    
                    // Parse JSON fields
                    $jsonFields = [
                        'allowed_package_types', 'base_limits', 'compliance_requirements', 
                        'tax_codes', 'available_addons', 'applicable_discounts'
                    ];
                    
                    foreach ($jsonFields as $field) {
                        if (isset($itemArray[$field]) && is_string($itemArray[$field])) {
                            $itemArray[$field] = json_decode($itemArray[$field], true) ?? [];
                        }
                    }
                    
                    // Sort addons by featured status and sort order
                    if (!empty($itemArray['available_addons'])) {
                        usort($itemArray['available_addons'], function($a, $b) {
                            // First sort by featured status (featured first)
                            if ($a['is_featured'] != $b['is_featured']) {
                                return $b['is_featured'] - $a['is_featured'];
                            }
                            // Then by sort order
                            if ($a['sort_order'] != $b['sort_order']) {
                                return $a['sort_order'] - $b['sort_order'];
                            }
                            // Finally by name
                            return strcmp($a['name'], $b['name']);
                        });
                    }
                    
                    // Sort discounts by value (highest first)
                    if (!empty($itemArray['applicable_discounts'])) {
                        usort($itemArray['applicable_discounts'], function($a, $b) {
                            if ($a['value'] != $b['value']) {
                                return $b['value'] - $a['value'];
                            }
                            return strcmp($a['name'], $b['name']);
                        });
                    }
                    
                    // Legacy support - map features from base_limits
                    $itemArray['features'] = $itemArray['base_limits'] ?? [];
                    
                    // Calculate effective pricing
                    $itemArray['effective_price'] = $itemArray['price'];
                    $itemArray['has_discount'] = false;
                    $itemArray['discount_percentage'] = null;
                    
                    // Check for applicable discounts to calculate discount_price
                    if (!empty($itemArray['applicable_discounts'])) {
                        $bestDiscount = collect($itemArray['applicable_discounts'])
                            ->filter(function($discount) {
                                return $discount['is_public'] || !$discount['is_first_time_only'];
                            })
                            ->sortByDesc('value')
                            ->first();
                        
                        if ($bestDiscount) {
                            if ($bestDiscount['type'] === 'percentage') {
                                $discountAmount = ($itemArray['price'] * $bestDiscount['value']) / 100;
                                $itemArray['discount_price'] = $itemArray['price'] - $discountAmount;
                                $itemArray['discount_percentage'] = $bestDiscount['value'];
                            } else {
                                $itemArray['discount_price'] = max(0, $itemArray['price'] - $bestDiscount['value']);
                                $itemArray['discount_percentage'] = round((($itemArray['price'] - $itemArray['discount_price']) / $itemArray['price']) * 100, 1);
                            }
                            $itemArray['effective_price'] = $itemArray['discount_price'];
                            $itemArray['has_discount'] = true;
                        }
                    }
                    
                    // Add computed fields for backward compatibility
                    $itemArray['savings'] = $itemArray['has_discount'] ? 
                        ($itemArray['price'] - $itemArray['effective_price']) : 0;
                    
                    return $itemArray;
                })->toArray();

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Tenant packages list fetched successfully',
                    'data' => $response,
                    'filters' => [
                        'package_type' => $packageType,
                        'billing_cycle' => $billingCycle,
                        'region' => $region,
                        'package_id' => $tenantPackagesId
                    ]
                ];
            }

            DB::rollBack();
            return [
                'success' => false,
                'message' => 'No matching tenant packages found.',
                'data' => [],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TenantPackagesRepository::getAllTenantPackages - Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function getPackagesWithAddonsAndDiscounts(?string $packageType = null, ?string $billingCycle = null, ?string $region = null)
    {
        try {
            // Get packages with addons and discounts included
            $packagesResult = $this->getAllTenantPackages(null, $packageType, $billingCycle, $region);
            
            if (!$packagesResult['success']) {
                return $packagesResult;
            }

            $packages = collect($packagesResult['data']);

            // Group by package name for better organization
            $groupedPackages = $packages->groupBy('name')->map(function ($packageGroup) {
                $firstPackage = $packageGroup->first();
                
                // Find monthly and yearly versions - Fix the collection filtering
                $monthly = $packageGroup->filter(function($pkg) {
                    return $pkg['billing_type'] === 'monthly' || $pkg['billing_type'] === 'both';
                })->first();
                
                $yearly = $packageGroup->filter(function($pkg) {
                    return $pkg['billing_type'] === 'yearly' || $pkg['billing_type'] === 'both';
                })->first();
                
                // If billing_type is 'both', use the same package for both
                if ($firstPackage['billing_type'] === 'both') {
                    $monthlyData = $firstPackage;
                    $monthlyData['price'] = $firstPackage['base_price_monthly'];
                    $monthlyData['type'] = 'month';
                    
                    $yearlyData = $firstPackage;
                    $yearlyData['price'] = $firstPackage['base_price_yearly'];
                    $yearlyData['type'] = 'year';
                    
                    $monthly = $monthlyData;
                    $yearly = $yearlyData;
                }
                
                return [
                    'name' => $firstPackage['name'],
                    'slug' => $firstPackage['slug'] ?? null,
                    'description' => $firstPackage['description'],
                    'allowed_package_types' => $firstPackage['allowed_package_types'],
                    'features' => $firstPackage['features'],
                    'base_limits' => $firstPackage['base_limits'] ?? [],
                    'support' => $firstPackage['support'] ?? false,
                    'is_popular' => $firstPackage['is_popular'],
                    'monthly' => $monthly,
                    'yearly' => $yearly,
                    'trial_days' => $firstPackage['trial_days'],
                    'setup_fee' => $firstPackage['setup_fee'],
                    'charge_immediately_on_signup' => $firstPackage['charge_immediately_on_signup'] ?? true,
                    'available_addons' => $firstPackage['available_addons'] ?? [],
                    'applicable_discounts' => $firstPackage['applicable_discounts'] ?? [],
                    // Add billing_type for the new structure
                    'billing_type' => $firstPackage['billing_type'] ?? 'both',
                    'base_price_monthly' => $firstPackage['base_price_monthly'] ?? 0,
                    'base_price_yearly' => $firstPackage['base_price_yearly'] ?? 0,
                ];
            })->values();

            // Extract unique addons across all packages
            $allAddons = collect();
            $packages->each(function($package) use ($allAddons) {
                if (!empty($package['available_addons'])) {
                    $allAddons->push(...$package['available_addons']);
                }
            });
            
            $uniqueAddons = $allAddons->unique('id')->values();

            // Extract unique discounts across all packages  
            $allDiscounts = collect();
            $packages->each(function($package) use ($allDiscounts) {
                if (!empty($package['applicable_discounts'])) {
                    $allDiscounts->push(...$package['applicable_discounts']);
                }
            });
            
            $uniqueDiscounts = $allDiscounts->unique('id')->values();

            return [
                'success' => true,
                'message' => 'Packages with addons and discounts fetched successfully',
                'data' => [
                    'packages' => $groupedPackages,
                    'addons' => $uniqueAddons,
                    'discounts' => $uniqueDiscounts,
                ],
                'filters' => [
                    'package_type' => $packageType,
                    'billing_cycle' => $billingCycle,
                    'region' => $region
                ]
            ];

        } catch (\Exception $e) {
            Log::error('TenantPackagesRepository::getPackagesWithAddonsAndDiscounts - Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function validateDiscountCode(string $discountCode, int $packageId, ?string $billingCycle = null)
    {
        try {
            $discount = DB::table('package_discounts')
                ->where('code', $discountCode)
                ->where('isactive', true)
                ->where('approval_status', 'approved')
                ->where(function($query) {
                    $query->whereNull('valid_from')->orWhere('valid_from', '<=', now());
                })
                ->where(function($query) {
                    $query->whereNull('valid_until')->orWhere('valid_until', '>=', now());
                })
                ->first();

            if (!$discount) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired discount code',
                    'data' => null,
                ];
            }

            // Check package compatibility
            $package = DB::table('tenant_packages')->where('id', $packageId)->first();
            if (!$package) {
                return [
                    'success' => false,
                    'message' => 'Package not found',
                    'data' => null,
                ];
            }

            // Check if discount applies to this package
            if ($discount->applicable_package_slugs) {
                $applicablePackages = json_decode($discount->applicable_package_slugs, true);
                if (!in_array($package->slug, $applicablePackages)) {
                    return [
                        'success' => false,
                        'message' => 'Discount code not applicable to this package',
                        'data' => null,
                    ];
                }
            }

            // Check if package is excluded
            if ($discount->excluded_package_slugs) {
                $excludedPackages = json_decode($discount->excluded_package_slugs, true);
                if (in_array($package->slug, $excludedPackages)) {
                    return [
                        'success' => false,
                        'message' => 'Discount code not applicable to this package',
                        'data' => null,
                    ];
                }
            }

            // Check billing cycle compatibility
            if ($billingCycle && $discount->applicable_billing_cycles !== 'both') {
                if ($discount->applicable_billing_cycles !== $billingCycle) {
                    return [
                        'success' => false,
                        'message' => 'Discount code not applicable to this billing cycle',
                        'data' => null,
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'Discount code is valid',
                'data' => [
                    'id' => $discount->id,
                    'code' => $discount->code,
                    'name' => $discount->name,
                    'type' => $discount->type,
                    'value' => $discount->value,
                    'maximum_discount_amount' => $discount->maximum_discount_amount,
                    'minimum_amount' => $discount->minimum_amount,
                    'apply_to_setup_fees' => $discount->apply_to_setup_fees,
                    'apply_to_addons' => $discount->apply_to_addons,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('TenantPackagesRepository::validateDiscountCode - Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to validate discount code',
                'data' => null,
            ];
        }
    }

    public function calculatePricing(int $packageId, array $selectedAddons = [], ?string $discountCode = null, ?string $billingCycle = 'monthly')
    {
        try {
            DB::beginTransaction();

            // Get package details
            $packageResult = $this->getAllTenantPackages($packageId, null, $billingCycle);
            if (!$packageResult['success'] || empty($packageResult['data'])) {
                throw new \Exception('Package not found');
            }

            $package = $packageResult['data'][0];
            $isYearly = $billingCycle === 'yearly';
            
            // Base calculations
            $basePrice = $isYearly ? $package['base_price_yearly'] : $package['base_price_monthly'];
            $setupFee = $package['setup_fee'] ?? 0;
            $addonTotal = 0;
            $addonDetails = [];

            // Calculate addon costs
            foreach ($selectedAddons as $addonSelection) {
                $addonId = $addonSelection['addon_id'];
                $quantity = $addonSelection['quantity'] ?? 1;

                $addon = collect($package['available_addons'] ?? [])->firstWhere('id', $addonId);
                if (!$addon) {
                    continue;
                }

                $addonPrice = $isYearly ? $addon['price_yearly'] : $addon['price_monthly'];
                $addonCost = $addonPrice * $quantity;
                $addonTotal += $addonCost;

                $addonDetails[] = [
                    'addon_id' => $addonId,
                    'name' => $addon['name'],
                    'quantity' => $quantity,
                    'unit_price' => $addonPrice,
                    'total_price' => $addonCost,
                ];
            }

            $subtotal = $basePrice + $addonTotal;
            $discountAmount = 0;
            $discountDetails = null;

            // Apply discount if provided
            if ($discountCode) {
                $discountResult = $this->validateDiscountCode($discountCode, $packageId, $billingCycle);
                if ($discountResult['success']) {
                    $discount = $discountResult['data'];
                    
                    // Check minimum amount
                    if ($discount['minimum_amount'] && $subtotal < $discount['minimum_amount']) {
                        throw new \Exception("Minimum order amount of $" . $discount['minimum_amount'] . " required for this discount");
                    }

                    // Calculate discount amount
                    if ($discount['type'] === 'percentage') {
                        $discountAmount = ($subtotal * $discount['value']) / 100;
                    } else {
                        $discountAmount = $discount['value'];
                    }

                    // Apply maximum discount limit
                    if ($discount['maximum_discount_amount'] && $discountAmount > $discount['maximum_discount_amount']) {
                        $discountAmount = $discount['maximum_discount_amount'];
                    }

                    // Setup fee discount
                    $setupFeeDiscount = 0;
                    if ($discount['apply_to_setup_fees'] && $setupFee > 0) {
                        if ($discount['type'] === 'percentage') {
                            $setupFeeDiscount = ($setupFee * $discount['value']) / 100;
                        } else {
                            $setupFeeDiscount = min($setupFee, $discount['value']);
                        }
                    }

                    $discountDetails = [
                        'code' => $discount['code'],
                        'name' => $discount['name'],
                        'type' => $discount['type'],
                        'value' => $discount['value'],
                        'amount' => $discountAmount,
                        'setup_fee_discount' => $setupFeeDiscount,
                    ];

                    $setupFee -= $setupFeeDiscount;
                }
            }

            $finalTotal = max(0, $subtotal - $discountAmount);
            $totalWithSetup = $finalTotal + $setupFee;

            DB::commit();

            return [
                'success' => true,
                'message' => 'Pricing calculated successfully',
                'data' => [
                    'package' => [
                        'id' => $package['id'],
                        'name' => $package['name'],
                        'base_price' => $basePrice,
                    ],
                    'addons' => $addonDetails,
                    'pricing' => [
                        'base_price' => $basePrice,
                        'addon_total' => $addonTotal,
                        'subtotal' => $subtotal,
                        'discount_amount' => $discountAmount,
                        'setup_fee' => $setupFee,
                        'total' => $finalTotal,
                        'total_with_setup' => $totalWithSetup,
                        'billing_cycle' => $billingCycle,
                    ],
                    'discount' => $discountDetails,
                    'breakdown' => [
                        'currency' => 'USD',
                        'tax_inclusive' => false,
                        'calculated_at' => now()->toISOString(),
                    ],
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TenantPackagesRepository::calculatePricing - Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }
}
