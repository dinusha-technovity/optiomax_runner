<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageAddon extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'addon_type',
        'target_feature',
        'price_monthly',
        'price_yearly',
        'boost_values',
        'applicable_package_slugs',
        'excluded_package_slugs',
        'applicable_package_types',
        'regional_restrictions',
        'is_stackable',
        'max_quantity',
        'min_quantity',
        'requires_approval',
        'auto_scale',
        'is_metered',
        'metered_rate',
        'metered_unit',
        'prorated_billing',
        'compliance_requirements',
        'terms_conditions',
        'legal_version',
        'stripe_price_id_monthly',
        'stripe_price_id_yearly',
        'stripe_product_id',
        'stripe_meter_id',
        'isactive',
        'is_featured',
        'sort_order',
        'available_from',
        'available_until',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'boost_values' => 'array',
        'applicable_package_slugs' => 'array',
        'excluded_package_slugs' => 'array',
        'applicable_package_types' => 'array',
        'regional_restrictions' => 'array',
        'compliance_requirements' => 'array',
        'is_stackable' => 'boolean',
        'requires_approval' => 'boolean',
        'auto_scale' => 'boolean',
        'is_metered' => 'boolean',
        'prorated_billing' => 'boolean',
        'isactive' => 'boolean',
        'is_featured' => 'boolean',
        'available_from' => 'datetime',
        'available_until' => 'datetime'
    ];

    public function subscriptionAddons()
    {
        return $this->hasMany(SubscriptionAddon::class, 'addon_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isAvailable()
    {
        $now = now();
        return $this->isactive 
            && (!$this->available_from || $this->available_from <= $now)
            && (!$this->available_until || $this->available_until >= $now);
    }

    public function isApplicableToPackage($packageSlug)
    {
        if ($this->excluded_package_slugs && in_array($packageSlug, $this->excluded_package_slugs)) {
            return false;
        }

        if ($this->applicable_package_slugs && !in_array($packageSlug, $this->applicable_package_slugs)) {
            return false;
        }

        return true;
    }
}
