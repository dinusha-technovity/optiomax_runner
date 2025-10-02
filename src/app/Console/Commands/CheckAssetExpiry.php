<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Jobs\SendAssetActionEmailJob;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Mail\SupplierQuotationExpiryMail;
use Illuminate\Support\Facades\Log;

class CheckAssetExpiry extends Command
{
    protected $signature = 'check:asset-expiry';
    protected $description = 'Check assets and supplier quotations for upcoming expirations and notify relevant users.';

    public function handle()
    {
        $this->info('Starting Expiry Checker...');


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

        $today = Carbon::now();
        $targetDate = $today->copy()->addDays(7)->toDateString();
        $quotationReminderDate = $today->copy()->addDay()->toDateString(); // 1 day before expiry

        $this->checkExpiringAssets($db, $tenant, $targetDate, $today);
        $this->checkSupplierQuotations($db, $tenant, $quotationReminderDate, $today);
        $this->runAssetItemDepreciations($db, $tenant, $today);
    }

    protected function checkExpiringAssets($db, $tenant, $targetDate, $today)
    {
        $page = 1;
        $perPage = 500;

        do {
            $assets = $db->table('asset_items')
                ->where(function ($query) use ($targetDate) {
                    $query->where(function ($q) use ($targetDate) {
                        $q->whereDate('warranty_exparing_at', $targetDate)
                            ->whereIn('warrenty_condition_type_id', [1, 3]);
                    })->orWhere(function ($q) use ($targetDate) {
                        $q->whereDate('insurance_exparing_at', $targetDate);
                    });
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
                    $this->insertAssetActionAndNotify($db, $asset->id, 'warranty_expared', $tenant, $today);
                }

                if ($insuranceExpiring) {
                    $this->insertAssetActionAndNotify($db, $asset->id, 'insurance_expared', $tenant, $today);
                }
            }

            $page++;
        } while ($assets->count() === $perPage);
    }

    protected function insertAssetActionAndNotify($db, $assetId, $source, $tenant, $today)
    {
        $exists = $db->table('asset_item_action_queries')
            ->where('asset_item', $assetId)
            ->whereDate('created_at', $today->toDateString())
            ->where('source', $source)
            ->where('tenant_id', $tenant->id)
            ->exists();

        if (!$exists) {
            $queryId = $db->table('asset_item_action_queries')->insertGetId([
                'asset_item' => $assetId,
                'reading_id' => 0,
                'recommendation_id' => 0,
                'source' => $source,
                'tenant_id' => $tenant->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            logger()->info("Asset action ID: {$tenant->db_name} {$queryId}");
            dispatch(new SendAssetActionEmailJob($tenant, $queryId))->onQueue('emails');
        }
    }

    protected function checkSupplierQuotations($db, $tenant, $expiryDate, $today)
    {
        $expiringRequests = $db->table('supplier_quotation_request')
            ->whereDate('expires_at', $expiryDate)
            ->where('request_status', 'pending')
            ->whereNull('deleted_at')
            ->where('isactive', true)
            ->get();

        foreach ($expiringRequests as $request) {
            // prevent duplicate emails
            $alreadySent = $db->table('supplier_email_logs')
                ->where('reference_id', $request->id)
                ->where('type', 'quotation_expiry')
                ->whereDate('created_at', $today->toDateString())
                ->exists();

            if (!$alreadySent) {
                Mail::to($request->email)->queue(new SupplierQuotationExpiryMail($request));

                // Optional: Log it to prevent resending
                $db->table('supplier_email_logs')->insert([
                    'reference_id' => $request->id,
                    'email' => $request->email,
                    'type' => 'quotation_expiry',
                    'tenant_id' => $tenant->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                logger()->info("Quotation expiry email sent to: {$request->email}");
            }
        }
    }

    protected function runAssetItemDepreciations($db, $tenant, $today)
    {
        // timer_start('database_queries_for_depreciation');
        try {

            // Log::debug("Running asset item depreciations for tenant: {$tenant->id} : date {$today->toDateString()}");
            // Calling the stored procedure to calculate daily depreciation

            logger()->debug("Running asset item depreciations for tenant: {$tenant->id} : date {$today->toDateString()}");
                $db->select("CALL generate_daily_depreciation(CAST(? AS BIGINT), CAST(? AS DATE))", [
                    $tenant->id,
                    $today->toDateString()
                ]);

            logger()->info("Depreciation calculation success for tenant id: {$tenant->id}");

            // timer_stop('database_queries_for_depreciation');

        } catch (\Throwable $th) {
            logger()->error("Error occurred while running asset item depreciations for tenant {$tenant->id}: {$th->getMessage()}");
            
        }
    }

}


