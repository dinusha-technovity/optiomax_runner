<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MasterEntryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MasterEntryController extends Controller
{
    protected $MasterEntryService;

    public function __construct(MasterEntryService $MasterEntryService)
    {
        $this->MasterEntryService = $MasterEntryService;
    }
    /**
     * Display a listing of the resource.
     */

    public function getAllCountryCodes()
    {
        try {
            $response = $this->MasterEntryService->getAllcountryCodes();
            return response()->json($response);

        } catch (\Throwable $th) {
            Log::error("Something Went wrong when retrieving country codes", $th->getMessage());
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }
   
}
