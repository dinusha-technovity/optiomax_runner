<?php

namespace App\Jobs;

use App\Models\PaymentRetryLog;
use App\Models\User;
use App\Mail\PaymentReminderMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendPaymentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $retryLogId;
    public $timeout = 60;
    public $tries = 3;

    public function __construct($retryLogId)
    {
        $this->retryLogId = $retryLogId;
    }

    public function handle()
    {
        $retryLog = PaymentRetryLog::with(['tenant', 'subscription.package'])->find($this->retryLogId);
        
        if (!$retryLog) {
            Log::warning("Payment retry log not found for reminder: {$this->retryLogId}");
            return;
        }

        if ($retryLog->reminder_sent) {
            Log::info("Reminder already sent for retry log: {$this->retryLogId}");
            return;
        }

        $tenant = $retryLog->tenant;
        $ownerUser = User::find($tenant->owner_user);

        if (!$ownerUser) {
            Log::error("Owner user not found for tenant {$tenant->id}");
            return;
        }

        try {
            Mail::to($ownerUser->email)->send(new PaymentReminderMail(
                $ownerUser,
                $tenant,
                $retryLog
            ));

            $retryLog->update([
                'reminder_sent' => true,
                'reminder_sent_at' => now()
            ]);

            $tenant->update([
                'last_payment_reminder_sent' => now()
            ]);

            Log::info("Payment reminder sent to {$ownerUser->email} for tenant {$tenant->id}");

        } catch (\Exception $e) {
            Log::error("Failed to send payment reminder for tenant {$tenant->id}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error("SendPaymentReminderJob failed for retry log {$this->retryLogId}: " . $exception->getMessage());
    }
}
