<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $this->registerSlowQueryLogger();

        // Ensure middleware aliases are registered even if cached or in IDE tooling.
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('auth.jwt', \App\Http\Middleware\JwtMiddleware::class);
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

    private function registerSlowQueryLogger(): void
    {
        if (!config('database.slow_query_log.enabled', false)) {
            return;
        }

        $thresholdMs = max(1, (int) config('database.slow_query_log.threshold_ms', 200));
        $sampleRate = max(1, (int) config('database.slow_query_log.sample_rate', 1));

        DB::listen(function (QueryExecuted $query) use ($thresholdMs, $sampleRate): void {
            if ($query->time < $thresholdMs) {
                return;
            }

            if ($sampleRate > 1 && random_int(1, $sampleRate) !== 1) {
                return;
            }

            $sql = method_exists($query, 'toRawSql') ? $query->toRawSql() : $query->sql;

            if (strlen($sql) > 4000) {
                $sql = substr($sql, 0, 4000) . '...';
            }

            $route = null;
            if (app()->bound('request')) {
                $request = request();
                $route = $request->method() . ' ' . $request->path();
            }

            Log::warning('Slow query detected', [
                'connection' => $query->connectionName,
                'time_ms' => round($query->time, 2),
                'route' => $route,
                'sql' => $sql,
            ]);
        });
    }
}
