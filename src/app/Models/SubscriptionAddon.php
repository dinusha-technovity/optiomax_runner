<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionAddon extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'addon_id',
        'quantity',
        'unit_price_monthly',
        'unit_price_yearly',
        'total_price_monthly',
        'total_price_yearly',
        'applied_boost_values',
        'original_limits',
        'new_limits',
        'billing_cycle',
        'status',
        'is_prorated',
        'prorated_amount',
        'is_metered',
        'current_usage',
        'metered_charges',
        'usage_reset_date',
        'requires_approval',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejection_reason',
        'stripe_subscription_item_id',
        'stripe_meter_event_id',
        'stripe_metadata',
        'activated_at',
        'canceled_at',
        'paused_at',
        'scheduled_change_date',
        'compliance_checks',
        'legal_version',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'applied_boost_values' => 'array',
        'original_limits' => 'array',
        'new_limits' => 'array',
        'stripe_metadata' => 'array',
        'compliance_checks' => 'array',
        'is_prorated' => 'boolean',
        'is_metered' => 'boolean',
        'requires_approval' => 'boolean',
        'usage_reset_date' => 'datetime',
        'approved_at' => 'datetime',
        'activated_at' => 'datetime',
        'canceled_at' => 'datetime',
        'paused_at' => 'datetime',
        'scheduled_change_date' => 'datetime'
    ];

    public function subscription()
    {
        return $this->belongsTo(TenantSubscription::class, 'subscription_id');
    }

    public function addon()
    {
        return $this->belongsTo(PackageAddon::class, 'addon_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isPendingApproval()
    {
        return $this->approval_status === 'pending';
    }
}
