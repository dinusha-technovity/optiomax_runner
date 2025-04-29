<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Jobs\SendAssetActionEmailJob;
use Carbon\Carbon;

class NotifyCriticallyBasedAssetSchedule extends Command
{
    protected $signature = 'notify:critically-based-asset-schedule';
    protected $description = 'Notify responsible persons based on asset maintenance schedules.';

    public function handle()
    {
        $this->info('Starting Asset Schedule Notifications...');

        $tenants = DB::table('tenants')->get();

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant);
        }
    }

    protected function processTenant($tenant)
    {
        $connectionName = 'tenant_' . $tenant->id;

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

        $now = Carbon::now();

        // Process asset_item_critically_based_maintain_schedules
        $schedules = $db->table('asset_item_critically_based_maintain_schedules')
            ->whereNull('deleted_at')
            ->where('isactive', true)
            ->get();

        foreach ($schedules as $schedule) {
            logger()->info("schedule: {$schedule->schedule}");
            if ($this->isDue($schedule->schedule, $now)) {
                logger()->info("asset_item: {$schedule->asset_item}");
                $assetItem = $db->table('asset_items')->where('id', $schedule->asset_item)->first();

                if (!$assetItem) {
                    continue;
                }

                $exists = $db->table('asset_item_action_queries')
                    ->where('asset_item', $assetItem->id)
                    ->whereDate('created_at', $now->toDateString())
                    ->where('source', 'asset_item_critically_based_schedule_check')
                    ->where('tenant_id', $tenant->id)
                    ->exists();

                if (!$exists) {
                    $queryId = $db->table('asset_item_action_queries')->insertGetId([
                        'asset_item' => $assetItem->id,
                        'reading_id' => 0,
                        'recommendation_id' => $schedule->id,
                        'source' => 'asset_item_critically_based_schedule_check',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    dispatch(new SendAssetActionEmailJob($tenant, $queryId))->onQueue('emails');
                }
            }
        }

        // Process asset_critically_based_maintain_schedules
        $groupSchedules = $db->table('asset_critically_based_maintain_schedules')
            ->whereNull('deleted_at')
            ->where('isactive', true)
            ->get();

        foreach ($groupSchedules as $schedule) {
            logger()->info("group schedule: {$schedule->schedule}");
            if ($this->isDue($schedule->schedule, $now)) {
                $assetItems = $db->table('asset_items')
                    ->join('assets', 'asset_items.asset_id', '=', 'assets.id')
                    ->where('assets.id', $schedule->asset)
                    ->select('asset_items.id')
                    ->get();

                foreach ($assetItems as $assetItem) {
                    $exists = $db->table('asset_item_action_queries')
                        ->where('asset_item', $assetItem->id)
                        ->where('tenant_id', $tenant->id)
                        ->whereDate('created_at', $now->toDateString())
                        ->where('source', 'asset_critically_based_schedule_check')
                        ->exists();

                    if (!$exists) {
                        $queryId = $db->table('asset_item_action_queries')->insertGetId([
                            'asset_item' => $assetItem->id,
                            'reading_id' => 0,
                            'recommendation_id' => $schedule->id,
                            'source' => 'asset_critically_based_schedule_check',
                            'tenant_id' => $tenant->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        dispatch(new SendAssetActionEmailJob($tenant, $queryId))->onQueue('emails');
                    }
                }
            }
        }
    }

    protected function isDue($schedule, Carbon $now)
    {
        switch ($schedule) {
            case 'hourly':
                return $now->minute === 0;
            case 'daily':
                return $now->hour === 0 && $now->minute === 0;
            case 'weekly':
                return $now->dayOfWeek === Carbon::MONDAY && $now->hour === 0 && $now->minute === 0;
            case 'monthly':
                return $now->day === 1 && $now->hour === 0 && $now->minute === 0;
            case 'yearly':
                return $now->dayOfYear === 1 && $now->hour === 0 && $now->minute === 0;
            default:
                return false;
        }
    }
    // protected function isDue($schedule, Carbon $now)
    // {
    //     return true; // <- force run all
    // }
}