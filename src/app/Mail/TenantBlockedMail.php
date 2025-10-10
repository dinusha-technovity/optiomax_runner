<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\tenants;

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
        return $this->subject('Account Suspended - Action Required')
                    ->view('emails.tenant-blocked')
                    ->with([
                        'userName' => $this->user->name,
                        'tenantName' => $this->tenant->tenant_name,
                        'supportUrl' => config('app.url') . '/support',
                        'paymentUrl' => config('app.url') . '/billing/payment-methods',
                    ]);
    }
}
