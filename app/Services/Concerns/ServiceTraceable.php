<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\Log;

trait ServiceTraceable
{
    protected function trace(string $method, callable $callback): mixed
    {
        $start = microtime(true);

        Log::info('Service method started', [
            'service' => static::class,
            'method' => $method,
        ]);

        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::error('Service method error', [
                'service' => static::class,
                'method' => $method,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            Log::info('Service method finished', [
                'service' => static::class,
                'method' => $method,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
        }
    }
}
