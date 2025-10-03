<?php

namespace App\Http\Controllers;

use App\Services\TenantPackagesService;
use Illuminate\Http\Request;

class TenantPackagesController extends Controller
{
    protected $TenantPackagesService;

    public function __construct(TenantPackagesService $TenantPackagesService)
    {
        $this->TenantPackagesService = $TenantPackagesService;
    }

    public function index(Request $request)
    {
        try {
            $packageType = $request->query('package_type'); // INDIVIDUAL or ENTERPRISE
            $billingCycle = $request->query('billing_cycle'); // Monthly or Yearly
            $packageId = $request->query('package_id');

            $response = $this->TenantPackagesService->getAllTenantPackages(
                $packageId, 
                $packageType, 
                $billingCycle
            );

            return response()->json($response);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function getPackagesWithAddons(Request $request)
    {
        try {
            $packageType = $request->query('package_type');
            $billingCycle = $request->query('billing_cycle');

            $response = $this->TenantPackagesService->getPackagesWithAddonsAndDiscounts(
                $packageType, 
                $billingCycle
            );

            return response()->json($response);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function getPackageDetails($id, Request $request)
    {
        try {
            $packageType = $request->query('package_type');
            $billingCycle = $request->query('billing_cycle');

            $response = $this->TenantPackagesService->getAllTenantPackages(
                $id, 
                $packageType, 
                $billingCycle
            );

            if ($response['success'] && !empty($response['data'])) {
                $package = $response['data'][0];
                
                // Get available addons for this package
                $addonsResult = $this->TenantPackagesService->getPackagesWithAddonsAndDiscounts($packageType, $billingCycle);
                $availableAddons = [];
                
                if ($addonsResult['success']) {
                    $allAddons = $addonsResult['data']['addons'];
                    $availableAddons = $allAddons->filter(function($addon) use ($id) {
                        return !$addon['applicable_packages'] || in_array($id, $addon['applicable_packages']);
                    });
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Package details fetched successfully',
                    'data' => [
                        'package' => $package,
                        'available_addons' => $availableAddons
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Package not found'
            ], 404);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
