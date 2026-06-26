<?php
// routes/web.php
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/up', fn () => response()->json(['status' => 'ok', 'app' => config('app.name'), 'version' => '1.0.1']));

// Maintenance mode check (called by React frontend before rendering)
Route::get('/status', function () {
    $maintenance = \App\Models\Setting::get('maintenance', false);
    return response()->json([
        'maintenance' => (bool) $maintenance,
        'message'     => \App\Models\Setting::get('maintenance_msg', 'We\'ll be back soon!'),
    ]);
});

// Sanctum CSRF cookie (required for SPA session auth)
// This is automatically registered by Sanctum via the statefulApi() middleware call
