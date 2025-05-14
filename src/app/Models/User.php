<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'user_name',
        'email',
        'name',
        'contact_no',
        'contact_no_code',
        'portal_contact_no',
        'portal_contact_no_code',
        'zip_code',
        'portal_user_zip_code',
        'portal_user_country',
        'portal_user_city',
        'portal_user_address',
        'city',
        'country',
        'profile_image',
        'website',
        'address',
        'email_verified_at',
        'portal_is_email_validate',
        'is_email_verified',
        'portal_password',
        'password',
        'employee_code',
        'security_question',
        'security_answer',
        'activation_code',
        'is_user_blocked',
        'first_login',
        'user_description',
        'status',
        'is_owner',
        'is_app_user', 
        'is_system_user',
        'system_user_expires_at', 
        'created_by',
        'tenant_id',
        'designation_id'
    ];

    protected $hidden = [
        'password',
        'portal_password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_user_blocked' => 'boolean',
        'is_trial_account' => 'boolean',
        'first_login' => 'datetime',
        'is_deleted' => 'boolean',
        'is_owner' => 'boolean',
    ];

    public function tenants()
    {
        return $this->belongsTo(tenants::class, 'tenant_id');
    }

    /**
     * Specify the dates that should be treated as carbon instances.
    */
    protected $dates = ['deleted_at'];

    /**
     * Override findForPassport to exclude soft deleted users.
     */
    public function findForPassport($username)
    {
        $user = $this->where('email', $username)->first();

        if (!$user || $user->trashed()) {
            throw new ModelNotFoundException("User not found or is deleted.");
        }

        return $user;
    }

    /**
     * Revoke all tokens on soft delete.
     */
    public static function boot()
    {
        parent::boot();

        static::deleting(function ($user) {
            // Revoke all tokens for the user
            $user->tokens()->each(function ($token) {
                $token->revoke();
            });
        });
    }

    // Define the many-to-many relationship with roles
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    // Get all permissions for the user by combining all role permissions
    public function getAllPermissions()
    {
        return $this->roles()
                    ->with('permissions')
                    ->get()
                    ->pluck('permissions')
                    ->flatten()
                    ->unique('id')
                    ->pluck('name');
    }

    // Check if the user has a specific permission
    public function hasPermission($permission)
    {
        return $this->getAllPermissions()->contains($permission);
    }
}