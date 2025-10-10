<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationDebug extends Model
{
    protected $table = 'tenant_registration_debugs';

    protected $fillable = [
        'owner_user_id',
        'selected_package_id',
        'tenant_addons',
        'package_type',
        'invited_users',
        'validated_user',
        'status', // new: pending, processing, completed, failed
        'error_message', // new: error details
    ];

    protected $casts = [
        'invited_users' => 'array',
        'validated_user' => 'array',
    ];

    public function selectedPackage(): BelongsTo
    {
        return $this->belongsTo(\App\Models\TenantPackage::class, 'selected_package_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_user_id');
    }
}