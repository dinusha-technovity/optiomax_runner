<?php

namespace App\Jobs;

use App\Models\tenants;
use App\Models\User;
use App\Mail\TenantBlockedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class BlockTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenantId;
    public $timeout = 60;
    public $tries = 3;

    public function __construct($tenantId)
    {
        $this->tenantId = $tenantId;
    }

    public function handle()
    {
        $tenant = tenants::find($this->tenantId);
        
        if (!$tenant) {
            Log::warning("Tenant not found for blocking: {$this->tenantId}");
            return;
        }

        $ownerUser = User::find($tenant->owner_user);

        if (!$ownerUser) {
            Log::error("Owner user not found for tenant {$tenant->id}");
            return;
        }

        try {
            Mail::to($ownerUser->email)->send(new TenantBlockedMail(
                $ownerUser,
                $tenant
            ));

            Log::info("Tenant blocked notification sent to {$ownerUser->email} for tenant {$tenant->id}");

        } catch (\Exception $e) {
            Log::error("Failed to send tenant blocked notification for tenant {$tenant->id}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error("BlockTenantJob failed for tenant {$this->tenantId}: " . $exception->getMessage());
    }
}
