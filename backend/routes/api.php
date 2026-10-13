<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProductController;
use App\Http\Middleware\RequireAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::get('/demo-accounts', function () {
    abort_unless(app()->environment(['local', 'testing']), 404);

    return response()->json(['data' => [
        ['label' => 'Administrador', 'email' => 'admin@chicoprince.com', 'password' => '123456'],
        ['label' => 'Cliente', 'email' => 'teste@chicoprince.com', 'password' => '654321'],
    ]]);
});

// SPA de mesma origem: sessão e CSRF também se aplicam às rotas JSON.
Route::middleware('web')->group(function () {
    Route::get('/csrf-cookie', fn () => response()->noContent());
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/banners', [BannerController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show'])->whereNumber('product');

    Route::middleware('auth:sanctum')->group(function () {
        Route::middleware(RequireAdmin::class)->group(function () {
            Route::get('/admin/banners', [BannerController::class, 'manage']);
            Route::post('/admin/banners', [BannerController::class, 'store']);
            Route::put('/admin/banners/{banner}', [BannerController::class, 'update'])->whereNumber('banner');
            Route::delete('/admin/banners/{banner}', [BannerController::class, 'destroy'])->whereNumber('banner');

            Route::post('/products', [ProductController::class, 'store']);
            Route::match(['put', 'patch'], '/products/{product}', [ProductController::class, 'update'])->whereNumber('product');
            Route::delete('/products/{product}', [ProductController::class, 'destroy'])->whereNumber('product');
        });
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/orders', [CheckoutController::class, 'store']);
    });
});
