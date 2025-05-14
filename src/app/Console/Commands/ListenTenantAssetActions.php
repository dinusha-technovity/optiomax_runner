<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Jobs\SendAssetActionEmailJob;

class ListenTenantAssetActions extends Command
{
    protected $signature = 'listen:tenant-asset-actions';
    protected $description = 'Poll asset item action queries and send emails for each tenant.';

    public function handle()
    {
        $this->info('Starting Tenant Asset Listener (Polling Mode)...');

        while (true) {
            $tenants = DB::table('tenants')->get();

            foreach ($tenants as $tenant) {
                $this->processTenant($tenant);
            }

            sleep(5); // Poll every 5 seconds
        }
    }

    protected function processTenant($tenant)
    {
        $connectionName = 'tenant_' . $tenant->id;

        config([
            'database.connections.' . $connectionName => [
                'driver' => 'pgsql',
                'host' => $tenant->db_host,
                'port' => 5432,
                'database' => $tenant->db_name,
                'username' => $tenant->db_user,
                'password' => $tenant->db_password,
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
            ]
        ]);

        DB::purge($connectionName);
        $db = DB::connection($connectionName);

        $actions = $db->table('asset_item_action_queries')
            ->whereNull('notified_at') // Only not yet notified
            ->where('tenant_id', $tenant->id)
            ->get();

        foreach ($actions as $action) {
            // Dispatch Job to send Email
            dispatch(new SendAssetActionEmailJob($tenant, $action->id));
            logger()->info("action id: {$action->id} {$tenant->db_name}");
            // Mark as Notified
            // $db->table('asset_item_action_queries')
            //     ->where('id', $action->id)
            //     ->update(['notified_at' => now()]);
        }
    }
}