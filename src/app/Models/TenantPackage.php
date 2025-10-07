<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantPackage extends Model
{
    protected $table = 'tenant_packages';

    protected $fillable = [
        'name',
        'type',
        'price',
        'discount_price',
        'description',
        'credits',
        'workflows',
        'users',
        'max_storage_gb',
        'support',
        'max_retry_attempts',
        'retry_interval_days',
        'grace_period_days',
        'allowed_package_types',
        'features',
        'is_recurring',
        'trial_days',
        'setup_fee',
        'stripe_price_id_monthly',
        'stripe_price_id_yearly',
        'stripe_product_id',
        'isactive',
        'is_popular',
        'sort_order',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'setup_fee' => 'decimal:2',
        'support' => 'boolean',
        'is_recurring' => 'boolean',
        'isactive' => 'boolean',
        'is_popular' => 'boolean',
        'allowed_package_types' => 'array',
        'features' => 'array',
        'deleted_at' => 'datetime'
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'package_id');
    }

    public function getAllowedPackageTypes(): array
    {
        return $this->allowed_package_types ?? ['INDIVIDUAL', 'ENTERPRISE'];
    }

    public function supportsPackageType(string $packageType): bool
    {
        return in_array($packageType, $this->getAllowedPackageTypes());
    }

    public function getEffectivePrice(): float
    {
        return $this->discount_price ?? $this->price;
    }

    public function hasDiscount(): bool
    {
        return !is_null($this->discount_price);
    }
}
