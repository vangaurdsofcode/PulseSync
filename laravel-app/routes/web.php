<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');
    Route::get('/dashboard/analytics', [DashboardController::class, 'analytics']);
    Route::get('/dashboard/leads', [LeadController::class, 'index']);
    Route::get('/dashboard/sites', [SiteController::class, 'index']);
    Route::post('/dashboard/sites', [SiteController::class, 'store']);
    Route::post('/dashboard/sites/{site}/token', [SiteController::class, 'rotateToken']);
    Route::delete('/dashboard/sites/{site}', [SiteController::class, 'destroy']);
});
