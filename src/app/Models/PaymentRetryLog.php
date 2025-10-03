<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRetryLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'stripe_invoice_id',
        'retry_attempt',
        'max_retries',
        'status',
        'next_retry_at',
        'last_retry_at',
        'failure_reasons',
        'amount',
        'currency',
        'reminder_sent',
        'reminder_sent_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'failure_reasons' => 'array',
        'next_retry_at' => 'timestamp',
        'last_retry_at' => 'timestamp',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'timestamp'
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(tenants::class, 'tenant_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'subscription_id');
    }

    public function hasRetriesLeft(): bool
    {
        return $this->retry_attempt < $this->max_retries;
    }

    public function isReadyForRetry(): bool
    {
        return $this->status === 'pending' && 
               $this->next_retry_at && 
               $this->next_retry_at <= now() &&
               $this->hasRetriesLeft();
    }

    public function shouldSendReminder(): bool
    {
        return !$this->reminder_sent && 
               $this->next_retry_at && 
               $this->next_retry_at->subDays(7) <= now();
    }
}
