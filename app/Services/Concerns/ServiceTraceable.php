<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\Log;

trait ServiceTraceable
{
    protected function trace(string $method, callable $callback): mixed
    {
        $start = microtime(true);
        $slowThresholdMs = 250;

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
            $durationMs = round((microtime(true) - $start) * 1000, 2);

            if ($durationMs >= $slowThresholdMs) {
                Log::warning('Slow service method', [
                    'service' => static::class,
                    'method' => $method,
                    'duration_ms' => $durationMs,
                ]);
            }
        }
    }
}
