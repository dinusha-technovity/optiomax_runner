<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LayoutItem;
use Illuminate\Support\Facades\DB;
use App\Services\PortalWidgetItemManagementService;

class PortalWidgetItemManagementController extends Controller
{
    protected $PortalWidgetItemManagementService;

    public function __construct(PortalWidgetItemManagementService $PortalWidgetItemManagementService)
    {
        $this->PortalWidgetItemManagementService = $PortalWidgetItemManagementService;
    }

    // public function index()
    // {
    //     DB::beginTransaction(); 

    //     try {
        
    //         DB::statement('CALL STORE_PROCEDURE_RETRIEVE_APP_DASHBOARD_LAYOUT_WIDGETS()');
    //         $dbLayout = DB::table('app_dashboard_layout_from_store_procedure')->select('*')->get();
            
    //         DB::commit();
            
    //         return response()->json(['data' => $dbLayout]);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         throw $e;
    //     }
    // }

    public function index()
    {
        try {
    
            $response = $this->PortalWidgetItemManagementService->getPortalDashboardLayoutWidgets();

            return response()->json($response);
        } catch (\Throwable $th) { 
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
        
    }

    // public function addOrUpdateDashboardLayout(Request $request)
    // {
    //     DB::beginTransaction();

    //     try {
    //         $data = $request->all();
        
    //         DB::statement('CALL STORE_PROCEDURE_INSERT_OR_UPDATE_DASHBOARD_APP_LAYOUT_WIDGET(?, ?, ?, ?, ?, ?, ?, ?)', [
    //             $data['x'],
    //             $data['y'],
    //             $data['w'],
    //             $data['h'], 
    //             $data['style'],
    //             $data['widget_id'],
    //             $data['widget_type'],
    //             $data['id'] ?? null
    //         ]);
            
    //         DB::commit();
            
    //         return response()->json(['data' => $data]);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         throw $e;
    //     }
    // }

    public function addOrUpdateDashboardLayout(Request $request)
    {
        // Validate the incoming request data
        $validated = $request->validate([
            'x' => 'required|numeric',
            'y' => 'required|numeric',
            'w' => 'required|numeric',
            'h' => 'required|numeric',
            'style' => 'required|string',
            'widget_id' => 'required|integer',
            'widget_type' => 'required|string',
            'id' => 'nullable|integer',
        ]);

        // Call the service function with the validated data
        $response = $this->PortalWidgetItemManagementService->addOrUpdatePortalDashboardLayout($validated);

        // Return a JSON response based on the service response
        if ($response['success']) {
            return response()->json([
                'success' => true,
                'message' => $response['message'],
                'widget_id' => $response['widget_id'],
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => $response['message'],
                'widget_id' => $response['widget_id'],
            ], 400);
        }
    }

    // public function destroy($layout_id)
    // {
    //     DB::beginTransaction();

    //     try {
    //         $layourId = $layout_id;
        
    //         DB::statement('CALL STORE_PROCEDURE_REMOVE_APP_DASHBOARD_LAYOUT_WIDGET(?)', [
    //             $layourId
    //         ]);
            
    //         DB::commit();
            
    //         return response()->json(['message' => "Successful remove"]);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         throw $e;
    //     }
    // }

    public function destroy($layout_id)
    {
        // Call the service function with the validated data
        $response = $this->PortalWidgetItemManagementService->removeDashboardLayoutWidget($layout_id);

        // Return a JSON response based on the service result
        if ($response['success']) {
            return response()->json([
                'success' => true,
                'message' => $response['message'],
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => $response['message'],
            ], 400);
        }
    }
}