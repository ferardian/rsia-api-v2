<?php

namespace App\Providers;

use App\Services\AesHasher;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        \Illuminate\Support\Facades\Auth::provider('aes.user.provider', function ($app, array $config) {
            return new AesUserProvider(new AesHasher(), $config['model']);
        });

        Passport::routes();

        // Default expiration: 60 minutes (Web)
        $expireTime = now()->addMinutes(300);
        $refreshExpireTime = now()->addDays(7);

        // Conditional expiration for Mobile App
        // Check custom header or other identifiers
        if (request()->header('X-App-Type') == 'mobile' || request()->input('is_mobile')) {
            $expireTime = now()->addDays(15);
            $refreshExpireTime = now()->addDays(30);
        }

        Passport::tokensExpireIn($expireTime);
        Passport::refreshTokensExpireIn($refreshExpireTime);
        Passport::personalAccessTokensExpireIn($expireTime);
    }
}
