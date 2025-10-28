<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class tenant_configuration extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
    */

    protected $table = 'tenant_configuration';

    protected $fillable = [
        'system_user_email',
        'system_user_password',
        'deleted_at',
        'isactive',
        'tenant_id',
        'configuration_details'
    ];

    protected $casts = [
        'configuration_details' => 'array',
    ];
}
