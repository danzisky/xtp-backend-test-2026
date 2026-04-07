<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public const HOME = '/backstage';

    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        $this->bootRoute();
    }

    public function bootRoute(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Tighter per-IP limit on the flip endpoint. A real game session has at most max_tries flips; 30/min is generous for legitimate play while still blocking automated scraping.
        RateLimiter::for('flip', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Campaign load creates a game session and reserves prizes — limit to 10 requests per minute per IP to prevent resource exhaustion.
        RateLimiter::for('campaign-load', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
