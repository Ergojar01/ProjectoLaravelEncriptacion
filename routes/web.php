<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SecureAuthController;
use App\Http\Controllers\UserAdminController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// 1. La raíz muestra el login; si ya hay sesión, manda al portal
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('mask');
    }

    return view('auth.login');
})->name('login');

// 2. Portal: la única URL que ve el usuario (/portal). Todo lo demás vive dentro del iframe.
Route::get('/Dashboard', fn () => view('mask'))->name('mask')->middleware('auth');

Route::middleware('guest')->group(function () {
    Route::post('/acceder', [SecureAuthController::class, 'login'])->name('login.store');

    Route::view('/registro', 'auth.register')->name('register');
    Route::post('/registro', [SecureAuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/salir', [SecureAuthController::class, 'logout'])->name('logout');

    // Pantallas internas: solo se muestran dentro del portal ('framed')
    Route::middleware('framed')->group(function () {
        Route::get('/sistema', [DashboardController::class, 'index'])->name('dashboard');

        // Perfil: cambio de contraseña con el cifrado propio del proyecto
        Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/perfil/clave', [ProfileController::class, 'updatePassword'])->name('profile.password');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::middleware('role:499')->get('/usuarios', [UserAdminController::class, 'index'])->name('users.index');

            Route::middleware('role:500')->group(function () {
                Route::post('/usuarios', [UserAdminController::class, 'store'])->name('users.store');
                Route::patch('/usuarios/{user}/nivel', [UserAdminController::class, 'updateLevel'])->name('users.level');
                Route::delete('/usuarios/{user}', [UserAdminController::class, 'destroy'])->name('users.destroy');
            });
        });
    });
});
