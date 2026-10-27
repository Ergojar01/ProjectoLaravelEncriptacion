<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SecureAuthController;
use App\Http\Controllers\UserAdminController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// 1. La raíz manda a los usuarios logueados a la máscara
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('mask');
    }
    return view('auth.login');
})->name('login');

// 2. Esta es la nueva ruta congelada que verá el usuario
Route::get('/portal', function () {
    return view('mask');
})->name('mask')->middleware('auth');

Route::middleware('guest')->group(function () {
    // 2. Cambiamos la URL interna de procesamiento para ocultar la palabra "login"
    Route::post('/acceder', [SecureAuthController::class, 'login'])->name('login.store');
    
    // (Opcional) Traducimos el registro para mantener la limpieza
    Route::view('/registro', 'auth.register')->name('register');
    Route::post('/registro', [SecureAuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/salir', [SecureAuthController::class, 'logout'])->name('logout');
    
    // 3. Cambiamos la URL visible de "/dashboard" a "/sistema"
    Route::get('/sistema', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        // También limpiamos las URLs de administración ocultando "/users" por "/usuarios"
        Route::middleware('role:499')->get('/usuarios', [UserAdminController::class, 'index'])->name('users.index');

        Route::middleware('role:500')->group(function () {
            Route::post('/usuarios', [UserAdminController::class, 'store'])->name('users.store');
            Route::patch('/usuarios/{user}/nivel', [UserAdminController::class, 'updateLevel'])->name('users.level');
            Route::delete('/usuarios/{user}', [UserAdminController::class, 'destroy'])->name('users.destroy');
        });
    });
});