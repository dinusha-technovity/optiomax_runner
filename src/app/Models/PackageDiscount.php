<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageDiscount extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'type',
        'value',
        'applicable_packages',
        'applicable_package_types',
        'billing_cycles',
        'is_first_time_only',
        'usage_limit',
        'usage_count',
        'usage_limit_per_customer',
        'minimum_amount',
        'valid_from',
        'valid_until',
        'stripe_coupon_id',
        'isactive'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'applicable_packages' => 'array',
        'applicable_package_types' => 'array',
        'minimum_amount' => 'decimal:2',
        'is_first_time_only' => 'boolean',
        'valid_from' => 'timestamp',
        'valid_until' => 'timestamp',
        'isactive' => 'boolean'
    ];

    public function subscriptionDiscounts(): HasMany
    {
        return $this->hasMany(SubscriptionDiscount::class, 'discount_id');
    }

    public function isValidForUse(): bool
    {
        if (!$this->isactive) {
            return false;
        }

        $now = now();
        
        if ($this->valid_from && $now < $this->valid_from) {
            return false;
        }
        
        if ($this->valid_until && $now > $this->valid_until) {
            return false;
        }
        
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function isApplicableToPackage($packageId, $packageType = null): bool
    {
        // Check package restriction
        if ($this->applicable_packages && !in_array($packageId, $this->applicable_packages)) {
            return false;
        }

        // Check package type restriction
        if ($this->applicable_package_types && $packageType && !in_array($packageType, $this->applicable_package_types)) {
            return false;
        }

        return true;
    }

    public function calculateDiscount($amount): float
    {
        if ($this->type === 'percentage') {
            return ($amount * $this->value) / 100;
        }
        
        return min($this->value, $amount); // Fixed amount, but not more than the total
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}
