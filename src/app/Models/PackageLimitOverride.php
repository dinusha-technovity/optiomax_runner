<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageLimitOverride extends Model
{
    protected $fillable = [
        'tenant_id',
        'package_id',
        'limit_type',
        'original_value',
        'override_value',
        'reason',
        'effective_from',
        'effective_until',
        'is_permanent',
        'created_by'
    ];

    protected $casts = [
        'effective_from' => 'timestamp',
        'effective_until' => 'timestamp',
        'is_permanent' => 'boolean'
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(tenants::class, 'tenant_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TenantPackage::class, 'package_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        $now = now();
        
        if ($this->effective_from && $now < $this->effective_from) {
            return false;
        }
        
        if (!$this->is_permanent && $this->effective_until && $now > $this->effective_until) {
            return false;
        }

        return true;
    }
}
