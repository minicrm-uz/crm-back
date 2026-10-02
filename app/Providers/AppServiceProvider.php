<?php

namespace App\Providers;

use App\Services\JwtTokenService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JwtTokenService::class, function ($app) {
            $config = $app['config']->get('jwt');

            return new JwtTokenService(
                secret: (string) $config['secret'],
                algo: (string) $config['algo'],
                accessTtl: (int) $config['access_ttl'],
                refreshTtl: (int) $config['refresh_ttl'],
                issuer: (string) $config['issuer'],
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
