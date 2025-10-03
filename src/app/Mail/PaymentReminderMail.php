<?php

namespace App\Mail;

use App\Models\User;
use App\Models\tenants;
use App\Models\PaymentRetryLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

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
        return $this->subject('Payment Reminder - Action Required')
                    ->view('emails.payment-reminder')
                    ->with([
                        'userName' => $this->user->name,
                        'tenantName' => $this->tenant->tenant_name,
                        'amount' => $this->retryLog->amount,
                        'dueDate' => $this->retryLog->next_retry_at,
                        'attemptsRemaining' => $this->retryLog->max_retries - $this->retryLog->retry_attempt,
                        'portalUrl' => env('PORTAL_URL')
                    ]);
    }
}
