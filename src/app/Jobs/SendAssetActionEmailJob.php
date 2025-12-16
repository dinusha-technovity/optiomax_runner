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
            $details = $conn->selectOne("SELECT * FROM get_asset_item_action_queries_details(NULL, NULL, NULL, ?) LIMIT 1", [
                $this->readingId,
            ]);

            if (!$details) {
                // Mark as notified to prevent reprocessing
                $conn->table('asset_item_action_queries')
                    ->where('id', $this->readingId)
                    ->update(['notified_at' => now()]);
                return;
            }

            if (!empty($details->responsible_personal_email)) {
                Mail::to($details->responsible_personal_email)->send(new AssetActionMail($details));

                $conn->table('asset_item_action_queries')
                    ->where('id', $this->readingId)
                    ->update(['notified_at' => now()]);

                logger()->info("Email sent successfully to: {$details->responsible_personal_email}");
            } else {
                logger()->warning("No email found for responsible person in action ID: {$this->readingId}");
            }
        } catch (\Exception $e) {
            logger()->error("SendAssetActionEmailJob failed for tenant {$this->tenant->id} and action ID {$this->readingId}: " . $e->getMessage());
        }
    }
}