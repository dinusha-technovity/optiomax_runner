<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'stripe_payment_intent_id',
        'stripe_invoice_id',
        'type',
        'amount',
        'currency',
        'status',
        'description',
        'stripe_response',
        'processed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'stripe_response' => 'array',
        'processed_at' => 'timestamp'
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(tenants::class, 'tenant_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'subscription_id');
    }
}
