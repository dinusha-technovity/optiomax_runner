<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantSubscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'package_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_payment_method_id',
        'billing_cycle',
        'status',
        'amount',
        'current_period_start',
        'current_period_end',
        'trial_end',
        'canceled_at',
        'metadata'
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'trial_end' => 'datetime',
        'canceled_at' => 'datetime',
        'metadata' => 'array'
    ];

    public function tenant()
    {
        return $this->belongsTo(tenants::class, 'tenant_id');
    }

    public function package()
    {
        return $this->belongsTo(TenantPackage::class, 'package_id');
    }

    public function addons()
    {
        return $this->hasMany(SubscriptionAddon::class, 'subscription_id');
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class, 'subscription_id');
    }

    public function paymentRetryLogs()
    {
        return $this->hasMany(PaymentRetryLog::class, 'subscription_id');
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isInTrial()
    {
        return $this->status === 'trialing' && $this->trial_end > now();
    }

    public function isPastDue()
    {
        return $this->status === 'past_due';
    }
}
