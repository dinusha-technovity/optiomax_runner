<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Passport\Client;

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
        'passport_client_id',
        'passport_client_secret',
        'optiomesh_public_api_key',
        'optiomesh_widget_domains', // Allowed domains for iframe embedding
    ];

    /**
     * Get the users that belong to this designation.
    */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Create a passport client for this tenant
     */
    public function createPassportClient()
    {
        $client = Client::create([
            'user_id' => null, // Client credentials don't belong to a user
            'name' => $this->tenant_name . ' Client Credentials',
            'secret' => \Illuminate\Support\Str::random(40),
            'provider' => null,
            'redirect' => '',
            'personal_access_client' => false,
            'password_client' => false,
            'revoked' => false,
        ]);

        $this->update([
            'passport_client_id' => $client->id,
            'passport_client_secret' => $client->secret,
        ]);

        return $client;
    }

    /**
     * Generate a public API key for widget access (like Google API keys)
     */
    public function generatePublicApiKey()
    {
        $publicKey = 'pk_' . \Illuminate\Support\Str::random(32);
        $this->update(['optiomesh_public_api_key' => $publicKey]);
        return $publicKey;
    }

    /**
     * Verify if domain is allowed for widget embedding
     */
    public function isDomainAllowed($domain)
    {
        if (!$this->optiomesh_widget_domains) return false;
        $allowedDomains = json_decode($this->optiomesh_widget_domains, true);
        return in_array($domain, $allowedDomains ?? []);
    }
}