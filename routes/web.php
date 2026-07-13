<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasswordController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// Auth (basic — full auth module lands later). showLogin() redirects logged-in users.
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Change password (FR-A06)
    Route::get('/ubah-sandi', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/ubah-sandi', [PasswordController::class, 'update'])->name('password.update');
});
