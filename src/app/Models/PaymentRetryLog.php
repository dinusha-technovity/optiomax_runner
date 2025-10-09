<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PaymentRetryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'subscription_id', 
        'package_id',
        'amount',
        'currency',
        'retry_attempt',
        'max_retry_attempts',
        'retry_interval_days',
        'grace_period_days',
        'status',
        'last_failure_reason',
        'failure_reasons',
        'next_retry_date',
        'grace_period_end',
        'stripe_error_code',
        'decline_code',
        'reminder_sent',
        'reminder_sent_at'
    ];

    protected $casts = [
        'failure_reasons' => 'array',
        'next_retry_date' => 'datetime',
        'grace_period_end' => 'datetime',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime'
    ];

    public function tenant()
    {
        return $this->belongsTo(tenants::class, 'tenant_id');
    }

    public function subscription()
    {
        return $this->belongsTo(TenantSubscription::class, 'subscription_id');
    }

    public function package()
    {
        return $this->belongsTo(TenantPackage::class, 'package_id');
    }

    public function isReadyForRetry()
    {
        return $this->status === 'pending' 
            && $this->retry_attempt < $this->max_retry_attempts 
            && $this->next_retry_date <= now()
            && $this->grace_period_end > now();
    }

    public function hasExceededMaxRetries()
    {
        return $this->retry_attempt >= $this->max_retry_attempts;
    }

    public function isInGracePeriod()
    {
        return $this->grace_period_end > now();
    }
}
