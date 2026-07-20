<?php

use App\Http\Controllers\Admin\AccessController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\ApplicationLinkController;
use App\Http\Controllers\Admin\QuestionnaireController as AdminQuestionnaireController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaunchController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\QuestionnaireController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// Auth (basic — full auth module lands later). showLogin() redirects logged-in users.
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/kuisioner/{questionnaire}/klik', [QuestionnaireController::class, 'click'])
        ->name('questionnaire.click');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Change password (FR-A06)
    Route::get('/ubah-sandi', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/ubah-sandi', [PasswordController::class, 'update'])->name('password.update');

    // Launch an application (FR-A10): access-enforced (403), records the visit
    Route::get('/launch/{application:slug}/{link?}', [LaunchController::class, 'launch'])
        ->whereNumber('link')
        ->name('launch');
});

// ===== Admin panel (HNR module) — auth + role='admin'; pegawai gets 403 =====
Route::middleware(['auth', EnsureUserIsAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Admin root: the panel's landing page (Manajemen Hak Akses is the core screen)
        Route::get('/', fn () => redirect()->route('admin.akses.index'))->name('home');

        // Manajemen Hak Akses — application_access per employee
        Route::get('/akses', [AccessController::class, 'index'])->name('akses.index');
        Route::get('/akses/{user}', [AccessController::class, 'edit'])->name('akses.edit');
        Route::put('/akses/{user}', [AccessController::class, 'update'])->name('akses.update');

        // Manajemen Aplikasi — CRUD applications
        Route::get('/aplikasi', [ApplicationController::class, 'index'])->name('aplikasi.index');
        Route::get('/aplikasi/create', [ApplicationController::class, 'create'])->name('aplikasi.create');
        Route::post('/aplikasi', [ApplicationController::class, 'store'])->name('aplikasi.store');
        Route::get('/aplikasi/{application}/edit', [ApplicationController::class, 'edit'])->name('aplikasi.edit');
        Route::put('/aplikasi/{application}', [ApplicationController::class, 'update'])->name('aplikasi.update');
        Route::delete('/aplikasi/{application}', [ApplicationController::class, 'destroy'])->name('aplikasi.destroy');

        // Manajemen Tautan Aplikasi — CRUD application_links (nested)
        Route::get('/aplikasi/{application}/link/create', [ApplicationLinkController::class, 'create'])->name('aplikasi.link.create');
        Route::post('/aplikasi/{application}/link', [ApplicationLinkController::class, 'store'])->name('aplikasi.link.store');
        Route::get('/aplikasi/{application}/link/{link}/edit', [ApplicationLinkController::class, 'edit'])->name('aplikasi.link.edit');
        Route::put('/aplikasi/{application}/link/{link}', [ApplicationLinkController::class, 'update'])->name('aplikasi.link.update');
        Route::delete('/aplikasi/{application}/link/{link}', [ApplicationLinkController::class, 'destroy'])->name('aplikasi.link.destroy');

        // Manajemen Pengguna — CRUD users (self-protection: no self deactivate/demote/delete)
        Route::get('/pengguna', [UserController::class, 'index'])->name('users.index');
        Route::get('/pengguna/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/pengguna', [UserController::class, 'store'])->name('users.store');
        Route::get('/pengguna/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/pengguna/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/pengguna/{user}/status', [UserController::class, 'status'])->name('users.status');
        Route::put('/pengguna/{user}/reset-sandi', [UserController::class, 'resetPassword'])->name('users.password');
        Route::delete('/pengguna/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // Log Aktivitas — read-only viewer (FR-A12)
        Route::get('/log-aktivitas', [ActivityLogController::class, 'index'])->name('logs.index');
        Route::get('/kuisioner/statistik', [AdminQuestionnaireController::class, 'statistics'])->name('questionnaires.statistics');
    });
