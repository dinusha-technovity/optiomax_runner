<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class tenants extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
    */
    protected $fillable = [
        'tenant_name',
        'address',
        'contact_no',
        'contact_no_code',
        // 'contact_Person_no',
        'email',
        'zip_code',
        'country',
        'city',
        'website',
        'owner_user',
        'is_tenant_blocked',
        'is_trial_tenant',
        'activate',
        'activation_code',
        'package',
        'db_host',
        'db_name',
        'db_user',
        'db_password',
        'updated_by',
        'optimesh_passport_client_id',
        'optimesh_passport_client_secret'
    ];

    /**
     * Get the users that belong to this designation.
    */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
