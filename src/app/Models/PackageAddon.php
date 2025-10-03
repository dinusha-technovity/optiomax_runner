<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageAddon extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'price_monthly',
        'price_yearly',
        'quantity',
        'applicable_packages',
        'is_stackable',
        'max_quantity',
        'stripe_price_id_monthly',
        'stripe_price_id_yearly',
        'isactive',
        'sort_order'
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'applicable_packages' => 'array',
        'is_stackable' => 'boolean',
        'isactive' => 'boolean'
    ];

    public function subscriptionAddons(): HasMany
    {
        return $this->hasMany(SubscriptionAddon::class, 'addon_id');
    }

    public function isApplicableToPackage($packageId): bool
    {
        if (!$this->applicable_packages) {
            return true; // If no restriction, applicable to all
        }
        
        return in_array($packageId, $this->applicable_packages);
    }

    public function getPriceForBillingCycle($billingCycle): float
    {
        return $billingCycle === 'yearly' ? $this->price_yearly : $this->price_monthly;
    }

    public function getStripePriceId($billingCycle): ?string
    {
        return $billingCycle === 'yearly' ? $this->stripe_price_id_yearly : $this->stripe_price_id_monthly;
    }
}
