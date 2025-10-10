<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\tenants;
use App\Models\PaymentRetryLog;

class PaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $tenant;
    public $retryLog;

    public function __construct(User $user, tenants $tenant, PaymentRetryLog $retryLog)
    {
        $this->user = $user;
        $this->tenant = $tenant;
        $this->retryLog = $retryLog;
    }

    public function build()
    {
        return $this->subject('Payment Reminder - Update Required')
                    ->view('emails.payment-reminder')
                    ->with([
                        'userName' => $this->user->name,
                        'tenantName' => $this->tenant->tenant_name,
                        'retryLog' => $this->retryLog,
                        'paymentUrl' => config('app.url') . '/billing/payment-methods',
                        'supportUrl' => config('app.url') . '/support',
                    ]);
    }
}
