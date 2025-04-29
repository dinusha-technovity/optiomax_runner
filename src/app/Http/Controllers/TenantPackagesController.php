<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TenantPackagesService;

class TenantPackagesController extends Controller
{
    protected $TenantPackagesService;

    public function __construct(TenantPackagesService $TenantPackagesService)
    {
        $this->TenantPackagesService = $TenantPackagesService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
    
            $response = $this->TenantPackagesService->getAllTenantPackages();

            return response()->json($response);
        } catch (\Throwable $th) {  
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
