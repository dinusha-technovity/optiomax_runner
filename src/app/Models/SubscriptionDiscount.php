<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionDiscount extends Model
{
    protected $fillable = [
        'subscription_id',
        'discount_id',
        'discount_amount',
        'original_amount',
        'final_amount',
        'stripe_discount_id',
        'applied_at',
        'expires_at',
        'is_active'
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'applied_at' => 'timestamp',
        'expires_at' => 'timestamp',
        'is_active' => 'boolean'
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'subscription_id');
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(PackageDiscount::class, 'discount_id');
    }

    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && now() > $this->expires_at) {
            return false;
        }

        return true;
    }
}
