<?php

use App\Http\Controllers\Api;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

// Logged-in users (every role)
Route::middleware('role')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Pages
    Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard');
    Route::get('/rooms', [PageController::class, 'rooms'])->name('rooms');
    Route::get('/maintenance', [PageController::class, 'maintenance'])->name('maintenance');
    Route::get('/account', [PageController::class, 'account'])->name('account');
    Route::get('/receipt', [PageController::class, 'receipt'])->name('receipt');
    Route::get('/file', [FileController::class, 'show'])->name('file');
    Route::get('/tenants', [PageController::class, 'tenants'])->middleware('role:admin,landlord')->name('tenants');

    // JSON API used by public/assets/js/app.js (role checks for each action are inside the controllers)
    Route::prefix('api')->group(function () {
        Route::get('/dashboard', [Api\DashboardController::class, 'index']);
        Route::get('/rooms', [Api\RoomController::class, 'index']);
        Route::post('/rooms', [Api\RoomController::class, 'store']);
        Route::get('/tenants', [Api\TenantController::class, 'index']);
        Route::post('/tenants', [Api\TenantController::class, 'store']);
        Route::get('/maintenance', [Api\MaintenanceController::class, 'index']);
        Route::post('/maintenance', [Api\MaintenanceController::class, 'store']);
        Route::get('/payments', [Api\PaymentController::class, 'index']);
        Route::post('/payments', [Api\PaymentController::class, 'store']);
        Route::post('/account', [Api\AccountController::class, 'store']);
    });
});
