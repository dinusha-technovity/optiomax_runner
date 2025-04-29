<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\AssetActionMail;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\AssetActionQueryService;

class SendAssetActionEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant;
    protected $readingId;

    public function __construct($tenant, $readingId)
    {
        $this->tenant = $tenant;
        $this->readingId = $readingId;
    }

    public function handle()
    {
        config([
            'database.connections.tenant' => [
                'driver' => 'pgsql',
                'host' => $this->tenant->db_host,
                'port' => 5432,
                'database' => $this->tenant->db_name,
                'username' => $this->tenant->db_user,
                'password' => $this->tenant->db_password,
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
            ]
        ]);

        DB::purge('tenant');
        $conn = DB::connection('tenant');

        try {
            // $service = app(AssetActionQueryService::class);

            // $actionData = $service->getAssetItemActionQueries(
            //     userId: null,
            //     tenantId: null,
            //     assetItemId: null,
            //     action_queries_id: $this->readingId
            // );

            // logger()->info('Fetched Action Data array: ' . json_encode($actionData, JSON_PRETTY_PRINT));

            $action = $conn->table('asset_item_action_queries')->where('id', $this->readingId)->first();

            if (!$action) {
                logger()->error("Action ID {$this->readingId} not found in tenant DB: {$this->tenant->db_name}");
                return;
            }

            // Defensive check: skip already notified actions
            if ($action->notified_at !== null) {
                logger()->info("Skipping already notified action ID {$this->readingId}");
                return;
            }

            $assetItem = $conn->table('asset_items')->where('id', $action->asset_item)->first();
            if (!$assetItem) {
                logger()->error("Asset item ID {$action->asset_item} not found in tenant DB.");
                return;
            }

            $user = $conn->table('users')->where('id', $assetItem->responsible_person)->first();
            if (!$user) {
                logger()->error("User ID {$assetItem->responsible_person} not found in tenant DB.");
                return;
            }

            logger()->info("Preparing to send email to: {$user->email}");

            Mail::to($user->email)->send(new AssetActionMail($user, $assetItem));

            logger()->info("Email sent successfully to: {$user->email}");

            $conn->table('asset_item_action_queries')
                ->where('id', $this->readingId)
                ->update(['notified_at' => now()]);
        } catch (\Exception $e) {
            logger()->error("SendAssetActionEmailJob failed for tenant {$this->tenant->id} and reading {$this->readingId}: " . $e->getMessage());
        }
    }
}