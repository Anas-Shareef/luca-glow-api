<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    | Requests from these domains receive session cookie authentication.
    | List every domain that hosts the React admin panel.
    |--------------------------------------------------------------------------
    */
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', implode(',', [
        'localhost',
        'localhost:3000',
        'localhost:8080',
        'localhost:5173',
        '127.0.0.1',
        '127.0.0.1:3000',
        '127.0.0.1:8080',
        '127.0.0.1:8000',
        env('APP_URL') ? parse_url(env('APP_URL'), PHP_URL_HOST) : null,
        env('FRONTEND_URL') ? parse_url(env('FRONTEND_URL'), PHP_URL_HOST) : null,
    ]))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    */
    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    | Token expiry in minutes. null = no expiry (rely on token revocation).
    | 10080 = 7 days — good balance for admin panel sessions.
    |--------------------------------------------------------------------------
    */
    'expiration' => 10080,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    | Prepended to tokens for easy identification in logs.
    |--------------------------------------------------------------------------
    */
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'lgadm_'),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies'      => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token'  => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
