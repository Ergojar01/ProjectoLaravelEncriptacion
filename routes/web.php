<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SecureAuthController;
use App\Http\Controllers\UserAdminController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'dashboard' : 'login'));

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [SecureAuthController::class, 'login'])->name('login.store');
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [SecureAuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SecureAuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        // Visualizador (499) y Administrador (500): solo consulta
        Route::middleware('role:499')->get('/users', [UserAdminController::class, 'index'])->name('users.index');

        // Solo Administrador (500): crear, modificar, eliminar
        Route::middleware('role:500')->group(function () {
            Route::post('/users', [UserAdminController::class, 'store'])->name('users.store');
            Route::patch('/users/{user}/level', [UserAdminController::class, 'updateLevel'])->name('users.level');
            Route::delete('/users/{user}', [UserAdminController::class, 'destroy'])->name('users.destroy');
        });
    });
});
