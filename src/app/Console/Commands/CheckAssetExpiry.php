<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Jobs\SendAssetActionEmailJob;
use Carbon\Carbon;

class CheckAssetExpiry extends Command
{
    protected $signature = 'check:asset-expiry';
    protected $description = 'Check assets for upcoming warranty or insurance expiry and notify responsible persons.';

    public function handle()
    {
        $this->info('Starting Asset Expiry Checker...');

        $tenants = DB::table('tenants')->get();

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant);
        }
    }

    protected function processTenant($tenant)
    {
        $connectionName = 'tenant_' . $tenant->id;

        // Cache tenant DB config
        $dbConfig = Cache::remember("tenant_db_config_{$tenant->id}", 3600, function () use ($tenant) {
            return [
                'driver' => 'pgsql',
                'host' => $tenant->db_host,
                'port' => 5432,
                'database' => $tenant->db_name,
                'username' => $tenant->db_user,
                'password' => $tenant->db_password,
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
            ];
        });

        config(['database.connections.' . $connectionName => $dbConfig]);

        DB::purge($connectionName);
        $db = DB::connection($connectionName);

        $today = Carbon::now();
        $targetDate = $today->copy()->addDays(7)->toDateString();

        $page = 1;
        $perPage = 500;

        do {
            $assets = $db->table('asset_items')
                ->where(function ($query) use ($targetDate) {
                    $query->whereDate('warranty_exparing_at', $targetDate)
                          ->orWhereDate('insurance_exparing_at', $targetDate);
                })
                ->whereNull('deleted_at')
                ->where('isactive', true)
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            foreach ($assets as $asset) {
                $warrantyExpiring = $asset->warranty_exparing_at === $targetDate;
                $insuranceExpiring = $asset->insurance_exparing_at === $targetDate;

                if ($warrantyExpiring) {
                    $source = 'warranty_expared';
                    $exists = $db->table('asset_item_action_queries')
                        ->where('asset_item', $asset->id)
                        ->whereDate('created_at', $today->toDateString())
                        ->where('source', $source)
                        ->where('tenant_id', $tenant->id)
                        ->exists();

                    if (!$exists) {
                        $queryId = $db->table('asset_item_action_queries')->insertGetId([
                            'asset_item' => $asset->id,
                            'reading_id' => 0,
                            'recommendation_id' => 0,
                            'source' => $source,
                            'tenant_id' => $tenant->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        logger()->info("action id: {$tenant->db_name} {$queryId}");
                        dispatch(new SendAssetActionEmailJob($tenant, $queryId))->onQueue('emails');
                    }
                }

                if ($insuranceExpiring) {
                    $source = 'insurance_expared';
                    $exists = $db->table('asset_item_action_queries')
                        ->where('asset_item', $asset->id)
                        ->whereDate('created_at', $today->toDateString())
                        ->where('source', $source)
                        ->where('tenant_id', $tenant->id)
                        ->exists();

                    if (!$exists) {
                        $queryId = $db->table('asset_item_action_queries')->insertGetId([
                            'asset_item' => $asset->id,
                            'reading_id' => 0,
                            'recommendation_id' => 0,
                            'source' => $source,
                            'tenant_id' => $tenant->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        logger()->info("action id: {$tenant->db_name} {$queryId}");
                        dispatch(new SendAssetActionEmailJob($tenant, $queryId))->onQueue('emails');
                    }
                }
            }

            $page++;
        } while ($assets->count() === $perPage);
    }
}