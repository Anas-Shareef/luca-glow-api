<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\SliderController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StorefrontController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\StorefrontOrderController;
use App\Http\Controllers\Api\PublicSettingsController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\FooterController;

Route::post('/auth/admin/login', [AuthController::class, 'login']);

// ─── Admin routes — temporarily moved out of auth for easy dev ────────────────
Route::prefix('v1')->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard/stats',    [DashboardController::class, 'stats']);
        Route::get('/dashboard/chart',    [DashboardController::class, 'salesChart']);
        Route::get('/dashboard/categories-chart', [DashboardController::class, 'categoryChart']);
        Route::get('/dashboard/low-stock',[DashboardController::class, 'lowStock']);
        Route::get('/dashboard/recent-orders', [DashboardController::class, 'recentOrders']);
        Route::get('/dashboard/notifications', [DashboardController::class, 'notifications']);

        // Products
        Route::get   ('/products',            [ProductController::class, 'index']);
        Route::post  ('/products',            [ProductController::class, 'store']);
        Route::get   ('/products/{product}',  [ProductController::class, 'show']);
        Route::put   ('/products/{product}',  [ProductController::class, 'update']);
        Route::delete('/products/{product}',  [ProductController::class, 'destroy']);
        Route::patch ('/products/{product}/toggle', [ProductController::class, 'toggleStatus']);
        Route::post  ('/products/{product}/media',  [ProductController::class, 'uploadMedia']);
        Route::delete('/products/{product}/media/{media}', [ProductController::class, 'deleteMedia']);
        Route::post  ('/products/{product}/media/reorder', [ProductController::class, 'reorderMedia']);

        // Categories
        Route::get   ('/categories',           [CategoryController::class, 'index']);
        Route::post  ('/categories',           [CategoryController::class, 'store']);
        Route::get   ('/categories/{category}',[CategoryController::class, 'show']);
        Route::put   ('/categories/{category}',[CategoryController::class, 'update']);
        Route::delete('/categories/{category}',[CategoryController::class, 'destroy']);
        Route::patch ('/categories/{category}/toggle', [CategoryController::class, 'toggleStatus']);

        // Orders
        Route::get('/orders',         [OrderController::class, 'index']);
        Route::get('/orders/refund-requests', [OrderController::class, 'refundRequests']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::patch ('/orders/{order}/status',  [OrderController::class, 'updateStatus']);
        Route::patch ('/orders/{order}/refund/approve', [OrderController::class, 'approveRefund']);
        Route::patch ('/orders/{order}/refund/reject',  [OrderController::class, 'rejectRefund']);
        Route::get   ('/orders/{order}/invoice', [OrderController::class, 'invoice']);
        Route::post  ('/orders/bulk-status',     [OrderController::class, 'bulkUpdateStatus']);
        Route::get   ('/orders/export/csv',      [OrderController::class, 'exportCsv']);
        Route::get   ('/orders/export/pdf',      [OrderController::class, 'exportPdf']);

        // Customers
        Route::get('/customers',              [CustomerController::class, 'index']);
        Route::get('/customers/{user}',       [CustomerController::class, 'show']);
        Route::patch('/customers/{user}/group', [CustomerController::class, 'updateGroup']);
        Route::patch('/customers/{user}/suspend', [CustomerController::class, 'suspend']);
        Route::post('/customers/{user}/reset-password', [CustomerController::class, 'sendPasswordReset']);
        Route::get('/customers/export/csv',   [CustomerController::class, 'exportCsv']);
        Route::get('/customers/export/pdf',   [CustomerController::class, 'exportPdf']);
        Route::delete('/customers/{user}',    [CustomerController::class, 'destroy']);

        // Reviews
        Route::prefix('reviews')->group(function () {
            Route::get('/', [ReviewController::class, 'index']);
            Route::post('/', [ReviewController::class, 'adminStore']);
            Route::put('/{id}', [ReviewController::class, 'update']);
            Route::delete('/{id}', [ReviewController::class, 'destroy']);
            Route::post('/bulk-delete', [ReviewController::class, 'bulkDelete']);
        });

        // Coupons
        Route::get   ('/coupons',          [CouponController::class, 'index']);
        Route::post  ('/coupons',          [CouponController::class, 'store']);
        Route::get   ('/coupons/{coupon}', [CouponController::class, 'show']);
        Route::put   ('/coupons/{coupon}', [CouponController::class, 'update']);
        Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy']);
        Route::patch ('/coupons/{coupon}/toggle', [CouponController::class, 'toggleStatus']);

        // Sliders
        Route::get   ('/sliders',          [SliderController::class, 'index']);
        Route::post  ('/sliders',          [SliderController::class, 'store']);
        Route::get   ('/sliders/{slider}', [SliderController::class, 'show']);
        Route::put   ('/sliders/{slider}', [SliderController::class, 'update']);
        Route::delete('/sliders/{slider}', [SliderController::class, 'destroy']);
        Route::patch ('/sliders/{slider}/toggle',  [SliderController::class, 'toggleStatus']);
        Route::post  ('/sliders/reorder',          [SliderController::class, 'reorder']);

        // Settings
        Route::get('/settings',        [SettingsController::class, 'index']);
        Route::put('/settings',        [SettingsController::class, 'update']);
        Route::post('/settings/media', [SettingsController::class, 'uploadMedia']);
        Route::get('/settings/promo',  [SettingsController::class, 'getPromo']);
        Route::post('/settings/promo', [SettingsController::class, 'updatePromo']);
        Route::post('/settings/cache/clear', [SettingsController::class, 'clearCache']);
        Route::get ('/staff',          [SettingsController::class, 'staff']);
        Route::post('/staff',         [SettingsController::class, 'createStaff']);
        Route::patch('/staff/{user}/role',   [SettingsController::class, 'updateRole']);
        Route::patch('/staff/{user}/toggle', [SettingsController::class, 'toggleStaff']);
        Route::delete('/staff/{user}',       [SettingsController::class, 'deleteStaff']);

        // Contact Messages
        Route::get('/contact-messages', [ContactMessageController::class, 'index']);
        Route::post('/contact-messages/{message}/reply', [ContactMessageController::class, 'reply']);
        Route::delete('/contact-messages/{message}', [ContactMessageController::class, 'destroy']);

        // Footer CMS
        Route::get   ('/footer-sections',          [FooterController::class, 'adminIndex']);
        Route::post  ('/footer-sections',          [FooterController::class, 'store']);
        Route::put   ('/footer-sections/{id}',     [FooterController::class, 'update']);
        Route::delete('/footer-sections/{id}',     [FooterController::class, 'destroy']);
        Route::post  ('/footer-sections/{id}/links',[FooterController::class, 'storeLink']);
        Route::put   ('/footer-links/{id}',        [FooterController::class, 'updateLink']);
        Route::delete('/footer-links/{id}',        [FooterController::class, 'destroyLink']);
    });

    // Auth
    Route::post('/auth/login',           [AuthController::class, 'login']);
    Route::post('/auth/register',        [AuthController::class, 'register']);
    Route::post('/auth/google',          [AuthController::class, 'googleLogin']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password',  [AuthController::class, 'resetPassword']);

    // Authenticated Storefront Routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/storefront/me',                      [AuthController::class, 'me']);
        Route::put('/storefront/profile',                  [AuthController::class, 'updateProfile']);
        Route::get('/storefront/orders',                   [OrderController::class, 'customerOrders']);
        // Addresses
        Route::get   ('/storefront/addresses',             [AddressController::class, 'index']);
        Route::post  ('/storefront/addresses',             [AddressController::class, 'store']);
        Route::put   ('/storefront/addresses/{address}',   [AddressController::class, 'update']);
        Route::delete('/storefront/addresses/{address}',   [AddressController::class, 'destroy']);
        Route::patch ('/storefront/addresses/{address}/default', [AddressController::class, 'setDefault']);
        // Checkout & Refunds
        Route::post('/storefront/checkout',                         [StorefrontOrderController::class, 'checkout']);
        Route::post('/storefront/orders/{order}/refund-request',    [StorefrontOrderController::class, 'requestRefund']);
        Route::post('/storefront/orders/{order}/cancel',            [StorefrontOrderController::class, 'cancelOrder']);
        Route::post('/storefront/reviews',                          [ReviewController::class, 'store']);

        // Admin Auth utils (if needed in v1)
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me',      [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    });

    Route::get('/storefront/data', [StorefrontController::class, 'data']);
    Route::get('/settings/public', PublicSettingsController::class);
    Route::get('/sliders/public',  [SliderController::class, 'index']);
    Route::post('/coupons/validate', [StorefrontController::class, 'validateCoupon']);
    Route::get('/storefront/products/{slug}/reviews', [ReviewController::class, 'productReviews']);
    Route::post('/contact', [ContactMessageController::class, 'store']);
    Route::get('/footer', [FooterController::class, 'index']);
});
