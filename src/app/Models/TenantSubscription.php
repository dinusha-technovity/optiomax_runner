<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantSubscription extends Model
{
    use SoftDeletes;

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
        'amount' => 'decimal:2',
        'current_period_start' => 'timestamp',
        'current_period_end' => 'timestamp',
        'trial_end' => 'timestamp',
        'canceled_at' => 'timestamp',
        'metadata' => 'array'
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(tenants::class, 'tenant_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TenantPackage::class, 'package_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'subscription_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInTrial(): bool
    {
        return $this->status === 'trialing' && $this->trial_end && $this->trial_end > now();
    }
}
