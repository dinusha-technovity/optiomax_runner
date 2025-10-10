<?php

namespace App\Services;

use App\Repositories\TenantPackagesRepository;
use Illuminate\Support\Facades\Log;

class TenantPackagesService
{
    protected $tenantPackagesRepository;

    public function __construct(TenantPackagesRepository $tenantPackagesRepository)
    {
        $this->tenantPackagesRepository = $tenantPackagesRepository;
    }

    public function getAllTenantPackages(?int $tenantPackagesId = null, ?string $packageType = null, ?string $billingCycle = null, ?string $region = null)
    {
        try {
            $result = $this->tenantPackagesRepository->getAllTenantPackages(
                $tenantPackagesId, 
                $packageType, 
                $billingCycle, 
                $region
            );
            
            return $result;
        } catch (\Exception $e) {
            Log::error('TenantPackagesService::getAllTenantPackages - Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch tenant packages: ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function getPackagesWithAddonsAndDiscounts(?string $packageType = null, ?string $billingCycle = null, ?string $region = null)
    {
        try {
            $result = $this->tenantPackagesRepository->getPackagesWithAddonsAndDiscounts(
                $packageType, 
                $billingCycle, 
                $region
            );
            
            return $result;
        } catch (\Exception $e) {
            Log::error('TenantPackagesService::getPackagesWithAddonsAndDiscounts - Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch packages with addons and discounts: ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function getPackageDetails(int $packageId, ?string $packageType = null, ?string $billingCycle = null, ?string $region = null)
    {
        try {
            // Get package details
            $packageResult = $this->getAllTenantPackages($packageId, $packageType, $billingCycle, $region);
            
            if (!$packageResult['success'] || empty($packageResult['data'])) {
                return [
                    'success' => false,
                    'message' => 'Package not found',
                    'data' => null,
                ];
            }

            $package = $packageResult['data'][0];
            
            // Enrich with pricing calculations
            $enrichedPackage = $this->enrichPackageWithPricing($package, $billingCycle);
            
            return [
                'success' => true,
                'message' => 'Package details fetched successfully',
                'data' => $enrichedPackage,
            ];
        } catch (\Exception $e) {
            Log::error('TenantPackagesService::getPackageDetails - Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch package details: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function validateDiscountCode(string $discountCode, int $packageId, ?string $billingCycle = null)
    {
        try {
            $result = $this->tenantPackagesRepository->validateDiscountCode($discountCode, $packageId, $billingCycle);
            return $result;
        } catch (\Exception $e) {
            Log::error('TenantPackagesService::validateDiscountCode - Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to validate discount code: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function calculatePricing(int $packageId, array $selectedAddons = [], ?string $discountCode = null, ?string $billingCycle = 'monthly')
    {
        try {
            $result = $this->tenantPackagesRepository->calculatePricing($packageId, $selectedAddons, $discountCode, $billingCycle);
            return $result;
        } catch (\Exception $e) {
            Log::error('TenantPackagesService::calculatePricing - Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to calculate pricing: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    private function enrichPackageWithPricing($package, ?string $billingCycle = null)
    {
        // Add computed pricing fields
        $package['monthly_price'] = $package['base_price_monthly'];
        $package['yearly_price'] = $package['base_price_yearly'];
        
        // Calculate yearly savings
        if ($package['base_price_yearly'] > 0 && $package['base_price_monthly'] > 0) {
            $yearlyEquivalent = $package['base_price_monthly'] * 12;
            $package['yearly_savings'] = $yearlyEquivalent - $package['base_price_yearly'];
            $package['yearly_savings_percentage'] = round(($package['yearly_savings'] / $yearlyEquivalent) * 100, 1);
        } else {
            $package['yearly_savings'] = 0;
            $package['yearly_savings_percentage'] = 0;
        }
        
        // Set current price based on billing cycle
        $package['current_price'] = $billingCycle === 'yearly' ? $package['yearly_price'] : $package['monthly_price'];
        
        return $package;
    }
}
