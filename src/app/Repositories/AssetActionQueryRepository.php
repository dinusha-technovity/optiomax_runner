<?php
namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
 
class AssetActionQueryRepository
{
    /**
     * Create an asset requisition and related items.
     *
     * @param array $data
     * @return void   
     */
    
    public function getAssetItemActionQueries(?int $userId = null, ?int $tenantId = null, ?int $assetItemId = null, ?int $action_queries_id = null): array
    {
        DB::beginTransaction();

        try {
            $result = DB::select(
                "SELECT * FROM get_asset_item_action_queries_details(?, ?, ?, ?)",
                [$userId, $tenantId, $assetItemId, $action_queries_id]
            );

            if (!empty($result)) {
                $response = collect($result)->map(function ($item) {
                    $item = (array) $item;

                    // if (!empty($item['reading_parameters'])) {
                    //     $decoded = json_decode($item['reading_parameters'], true);
                    //     $item['reading_parameters'] = json_last_error() === JSON_ERROR_NONE ? $decoded : $item['reading_parameters'];
                    // }

                    return $item;
                })->toArray();

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Asset item action queries fetched successfully',
                    'data' => $response,
                ];
            }

            DB::rollBack();
            return [
                'success' => false,
                'message' => 'No matching asset item action queries found.',
                'data' => [],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

}