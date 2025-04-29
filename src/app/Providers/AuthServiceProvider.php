<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;
use Carbon\Carbon;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define scopes here
        Passport::tokensCan([
            'access_portal' => 'Access protected resources',
            'refresh_portal' => 'Obtain new access tokens',
        ]);

        // Set token expiration times
        Passport::tokensExpireIn(Carbon::now()->addDays(7));      // Access token expiration
        Passport::refreshTokensExpireIn(Carbon::now()->addDays(30));  // Refresh token expiration
    }
}