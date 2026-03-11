<?php

return [
    'secret' => env('JWT_SECRET'),
    'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),
    'algo' => env('JWT_ALGO', 'HS256'),
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 900),
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 60 * 60 * 24 * 7),
    'single_refresh_token' => filter_var(
        env('JWT_SINGLE_REFRESH_TOKEN', true),
        FILTER_VALIDATE_BOOL
    ),
    'leeway' => (int) env('JWT_LEEWAY', 0),
];
