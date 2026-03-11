<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ensure middleware aliases are registered even if cached or in IDE tooling.
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('auth.jwt', \App\Http\Middleware\JwtMiddleware::class);
        $router->aliasMiddleware('auth.teacher', \App\Http\Middleware\TeacherJwtMiddleware::class);
        $router->aliasMiddleware('role', \App\Http\Middleware\RoleMiddleware::class);
        $router->aliasMiddleware('permission', \App\Http\Middleware\PermissionMiddleware::class);

        RateLimiter::for('login', function (Request $request) {
            $identifier = (string) ($request->input('username')
                ?? $request->input('login')
                ?? $request->input('email')
                ?? 'guest');
            $key = strtolower($identifier) . '|' . $request->ip();

            return Limit::perMinute(5)->by($key);
        });
    }
}
