<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JWT secret
    |--------------------------------------------------------------------------
    |
    | Symmetric key used by firebase/php-jwt for HMAC signing. Must be set to
    | a strong random value in production. Generate with:
    |   openssl rand -base64 64
    |
    */
    'secret' => env('JWT_SECRET'),

    'algo' => env('JWT_ALGO', 'HS256'),

    /*
    |--------------------------------------------------------------------------
    | Token lifetimes (seconds)
    |--------------------------------------------------------------------------
    |
    | Access tokens are short-lived and stateless. Refresh tokens are longer
    | and persisted (hashed) in the refresh_tokens table so they can be
    | rotated and revoked.
    |
    */
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 900),
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 2592000),

    'issuer' => env('APP_URL', 'http://localhost'),

];
