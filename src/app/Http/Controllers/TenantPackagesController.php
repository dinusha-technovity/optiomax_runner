<?php

namespace App\Http\Controllers;

use App\Services\TenantPackagesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TenantPackagesController extends Controller
{
    protected $tenantPackagesService;

    public function __construct(TenantPackagesService $tenantPackagesService)
    {
        $this->tenantPackagesService = $tenantPackagesService;
    }

    /**
     * Get all tenant packages with optional filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'package_type' => 'nullable|in:INDIVIDUAL,ENTERPRISE',
                'billing_cycle' => 'nullable|in:Monthly,Yearly,monthly,yearly',
                'package_id' => 'nullable|integer|min:1',
                'region' => 'nullable|string|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $packageType = $request->query('package_type');
            $billingCycle = $request->query('billing_cycle');
            $packageId = $request->query('package_id');
            $region = $request->query('region');

            $response = $this->tenantPackagesService->getAllTenantPackages(
                $packageId, 
                $packageType, 
                $billingCycle,
                $region
            );

            return response()->json($response, $response['success'] ? 200 : 404);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching packages: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Get packages with their available addons and discounts
     */
    public function getPackagesWithAddons(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'package_type' => 'nullable|in:INDIVIDUAL,ENTERPRISE',
                'billing_cycle' => 'nullable|in:Monthly,Yearly,monthly,yearly',
                'region' => 'nullable|string|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $packageType = $request->query('package_type');
            $billingCycle = $request->query('billing_cycle');
            $region = $request->query('region');

            $response = $this->tenantPackagesService->getPackagesWithAddonsAndDiscounts(
                $packageType, 
                $billingCycle,
                $region
            );

            return response()->json($response, $response['success'] ? 200 : 404);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching packages with addons: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed information about a specific package
     */
    public function getPackageDetails($id, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make(array_merge($request->all(), ['id' => $id]), [
                'id' => 'required|integer|min:1',
                'package_type' => 'nullable|in:INDIVIDUAL,ENTERPRISE',
                'billing_cycle' => 'nullable|in:Monthly,Yearly,monthly,yearly',
                'region' => 'nullable|string|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $packageType = $request->query('package_type');
            $billingCycle = $request->query('billing_cycle');
            $region = $request->query('region');

            $response = $this->tenantPackagesService->getPackageDetails(
                $id,
                $packageType, 
                $billingCycle,
                $region
            );

            return response()->json($response, $response['success'] ? 200 : 404);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching package details: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Validate a discount code for a specific package
     */
    public function validateDiscountCode(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'discount_code' => 'required|string|max:50',
                'package_id' => 'required|integer|min:1',
                'billing_cycle' => 'nullable|in:monthly,yearly',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $response = $this->tenantPackagesService->validateDiscountCode(
                $request->discount_code,
                $request->package_id,
                $request->billing_cycle
            );

            return response()->json($response, $response['success'] ? 200 : 400);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while validating discount code: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate pricing for a package with selected addons and discount
     */
    public function calculatePricing(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'package_id' => 'required|integer|min:1',
                'billing_cycle' => 'required|in:monthly,yearly',
                'addons' => 'nullable|array',
                'addons.*.addon_id' => 'required|integer|min:1',
                'addons.*.quantity' => 'nullable|integer|min:1|max:100',
                'discount_code' => 'nullable|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $response = $this->tenantPackagesService->calculatePricing(
                $request->package_id,
                $request->addons ?? [],
                $request->discount_code,
                $request->billing_cycle
            );

            return response()->json($response, $response['success'] ? 200 : 400);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while calculating pricing: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Get popular packages for homepage/landing page
     */
    public function getPopularPackages(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'package_type' => 'nullable|in:INDIVIDUAL,ENTERPRISE',
                'limit' => 'nullable|integer|min:1|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $packageType = $request->query('package_type');
            $limit = $request->query('limit', 3);

            // Get all packages and filter popular ones
            $response = $this->tenantPackagesService->getPackagesWithAddonsAndDiscounts($packageType);
            
            if ($response['success']) {
                $popularPackages = collect($response['data']['packages'])
                    ->where('is_popular', true)
                    ->take($limit)
                    ->values();

                $response['data']['packages'] = $popularPackages;
                $response['message'] = 'Popular packages fetched successfully';
            }

            return response()->json($response, $response['success'] ? 200 : 404);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching popular packages: ' . $th->getMessage()
            ], 500);
        }
    }
}
