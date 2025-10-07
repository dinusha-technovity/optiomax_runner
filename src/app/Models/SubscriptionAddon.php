<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionAddon extends Model
{
    protected $fillable = [
        'subscription_id',
        'addon_id',
        'quantity',
        'unit_price',
        'total_price',
        'stripe_subscription_item_id',
        'status',
        'activated_at',
        'canceled_at'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'activated_at' => 'timestamp',
        'canceled_at' => 'timestamp'
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'subscription_id');
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(PackageAddon::class, 'addon_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->canceled_at;
    }
}
