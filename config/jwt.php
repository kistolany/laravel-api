<?php

return [
    'secret' => env('JWT_SECRET'),
    'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),
    'algo' => env('JWT_ALGO', 'HS256'),
    // Keep the access token valid for one week (604,800 seconds) by default.
    // Override with JWT_ACCESS_TTL in production if a shorter lifetime is desired.
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 60 * 60 * 24 * 7),
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 60 * 60 * 24 * 7),
    'single_refresh_token' => filter_var(
        env('JWT_SINGLE_REFRESH_TOKEN', true),
        FILTER_VALIDATE_BOOL
    ),
    'leeway' => (int) env('JWT_LEEWAY', 0),
];
