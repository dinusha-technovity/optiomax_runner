<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\TenantPackage;
use App\Models\PackageAddon;
use App\Models\PackageDiscount;

class TenantPackagesService
{
    public function getAllTenantPackages(?int $TenantPackagesId = null, ?string $packageType = null, ?string $billingCycle = null)
    {
        DB::beginTransaction();

        try {
            // Convert billing cycle format if needed
            $dbBillingCycle = null;
            if ($billingCycle) {
                $dbBillingCycle = $billingCycle === 'Monthly' ? 'month' : 'year';
            }

            // Call the enhanced PostgreSQL function
            $result = DB::select(
                "SELECT * FROM get_tenant_packages_list(?, ?, ?)",
                [$TenantPackagesId, $packageType, $dbBillingCycle]
            );

            if (!empty($result)) {
                $response = collect($result)->map(function ($item) {
                    $itemArray = (array) $item;
                    
                    // Parse JSON fields
                    if (isset($itemArray['allowed_package_types']) && is_string($itemArray['allowed_package_types'])) {
                        $itemArray['allowed_package_types'] = json_decode($itemArray['allowed_package_types'], true);
                    }
                    if (isset($itemArray['features']) && is_string($itemArray['features'])) {
                        $itemArray['features'] = json_decode($itemArray['features'], true);
                    }
                    if (isset($itemArray['available_addons']) && is_string($itemArray['available_addons'])) {
                        $itemArray['available_addons'] = json_decode($itemArray['available_addons'], true);
                    }
                    
                    // Add computed fields
                    $itemArray['effective_price'] = $itemArray['discount_price'] ?? $itemArray['price'];
                    $itemArray['has_discount'] = !is_null($itemArray['discount_price']);
                    $itemArray['discount_percentage'] = null;
                    
                    if ($itemArray['has_discount'] && $itemArray['discount_price'] > 0) {
                        $itemArray['discount_percentage'] = round((($itemArray['discount_price'] - $itemArray['price']) / $itemArray['discount_price']) * 100, 1);
                    }
                    
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
                        'package_id' => $TenantPackagesId
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
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function getPackagesWithAddonsAndDiscounts(?string $packageType = null, ?string $billingCycle = null)
    {
        try {
            // Get packages
            $packagesResult = $this->getAllTenantPackages(null, $packageType, $billingCycle);
            
            if (!$packagesResult['success']) {
                return $packagesResult;
            }

            $packages = collect($packagesResult['data']);

            // Group by package name for better organization
            $groupedPackages = $packages->groupBy('name')->map(function ($packageGroup) {
                $monthly = $packageGroup->where('type', 'month')->first();
                $yearly = $packageGroup->where('type', 'year')->first();
                
                return [
                    'name' => $packageGroup->first()['name'],
                    'description' => $packageGroup->first()['description'],
                    'allowed_package_types' => $packageGroup->first()['allowed_package_types'],
                    'features' => $packageGroup->first()['features'],
                    'support' => $packageGroup->first()['support'],
                    'is_popular' => $packageGroup->first()['is_popular'],
                    'monthly' => $monthly,
                    'yearly' => $yearly,
                    'trial_days' => $packageGroup->first()['trial_days'],
                    'setup_fee' => $packageGroup->first()['setup_fee'],
                ];
            })->values();

            // Get available addons
            $addons = PackageAddon::where('isactive', true)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($addon) {
                    return [
                        'id' => $addon->id,
                        'name' => $addon->name,
                        'slug' => $addon->slug,
                        'description' => $addon->description,
                        'type' => $addon->type,
                        'price_monthly' => $addon->price_monthly,
                        'price_yearly' => $addon->price_yearly,
                        'quantity' => $addon->quantity,
                        'is_stackable' => $addon->is_stackable,
                        'max_quantity' => $addon->max_quantity,
                        'applicable_packages' => $addon->applicable_packages,
                    ];
                });

            // Get active discounts
            $discounts = PackageDiscount::where('isactive', true)
                ->where(function($query) {
                    $query->whereNull('valid_until')
                          ->orWhere('valid_until', '>', now());
                })
                ->where(function($query) {
                    $query->whereNull('valid_from')
                          ->orWhere('valid_from', '<=', now());
                })
                ->get()
                ->map(function ($discount) {
                    return [
                        'id' => $discount->id,
                        'name' => $discount->name,
                        'code' => $discount->code,
                        'description' => $discount->description,
                        'type' => $discount->type,
                        'value' => $discount->value,
                        'applicable_packages' => $discount->applicable_packages,
                        'applicable_package_types' => $discount->applicable_package_types,
                        'billing_cycles' => $discount->billing_cycles,
                        'is_first_time_only' => $discount->is_first_time_only,
                        'minimum_amount' => $discount->minimum_amount,
                        'valid_until' => $discount->valid_until,
                    ];
                });

            return [
                'success' => true,
                'message' => 'Packages with addons and discounts fetched successfully',
                'data' => [
                    'packages' => $groupedPackages,
                    'addons' => $addons,
                    'discounts' => $discounts,
                ],
                'filters' => [
                    'package_type' => $packageType,
                    'billing_cycle' => $billingCycle
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }
}
