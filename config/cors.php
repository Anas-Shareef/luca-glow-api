<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel CORS Configuration
    | Allows the React frontend (localhost:3000 in dev, lucaglow.com in prod)
    | to make authenticated requests to the Laravel API.
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
        'http://127.0.0.1:3000',
        'http://localhost:8080',
        'http://127.0.0.1:8080',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:5174',
        'http://127.0.0.1:5174',
        'http://localhost:3001',
        'http://127.0.0.1:3001',
        'https://lucaglow.com',
        'https://www.lucaglow.com',
        'https://admin.lucaglow.com',
    ],

    'allowed_origins_patterns' => [
        '/^https?:\/\/.*\.vercel\.app$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Content-Disposition',  // needed for CSV/PDF file downloads
    ],

    'max_age' => 0,

    // Must be true for Sanctum cookie-based session auth to work with SPA
    'supports_credentials' => true,

];
