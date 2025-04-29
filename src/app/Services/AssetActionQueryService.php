<?php
namespace App\Services;

use App\Repositories\AssetActionQueryRepository;

class AssetActionQueryService
{ 
    protected $AssetActionQueryRepository;

    public function __construct(AssetActionQueryRepository $AssetActionQueryRepository)
    {
        $this->AssetActionQueryRepository = $AssetActionQueryRepository;
    }

    /** 
     * Create an asset requisition and related items.
     *
     * @param array $data
     * @return void
     */

    public function getAssetItemActionQueries(?int $userId = null, ?int $tenantId = null, ?int $assetItemId = null, ?int $action_queries_id = null)
    {
        return $this->AssetActionQueryRepository->getAssetItemActionQueries($userId, $tenantId, $assetItemId, $action_queries_id);
    }
} 