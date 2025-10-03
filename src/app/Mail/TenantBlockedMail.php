<?php

namespace App\Mail;

use App\Models\User;
use App\Models\tenants;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantBlockedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $tenant;

    public function __construct(User $user, tenants $tenant)
    {
        $this->user = $user;
        $this->tenant = $tenant;
    }

    public function build()
    {
        return $this->subject('Account Suspended - Payment Required')
                    ->view('emails.tenant-blocked')
                    ->with([
                        'userName' => $this->user->name,
                        'tenantName' => $this->tenant->tenant_name,
                        'blockedAt' => $this->tenant->blocked_at,
                        'reason' => $this->tenant->blocking_reason,
                        'portalUrl' => env('PORTAL_URL'),
                        'supportEmail' => env('SUPPORT_EMAIL', 'support@optiomax.com')
                    ]);
    }
}
