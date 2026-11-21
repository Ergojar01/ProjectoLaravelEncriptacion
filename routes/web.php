<?php

use App\Http\Controllers\AuditController;
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
Route::get('/Dashboard', fn () => view('mask'))->name('mask')->middleware(['auth', 'active.account', 'auth.session']);

Route::middleware('guest')->group(function () {
    Route::post('/acceder', [SecureAuthController::class, 'login'])->name('login.store');

    Route::view('/registro', 'auth.register')->name('register');
    Route::view('/recuperar-clave', 'auth.recover')->name('password.recover');
    Route::post('/recuperar-clave', [SecureAuthController::class, 'requestTemp'])->middleware('throttle:5,10')->name('password.recover.store');
    Route::post('/registro', [SecureAuthController::class, 'register'])->name('register.store');
});

Route::middleware(['auth', 'active.account', 'auth.session', 'force.password'])->group(function () {
    Route::post('/salir', [SecureAuthController::class, 'logout'])->name('logout');

    // Pantallas internas: solo se muestran dentro del portal ('framed')
    Route::middleware('framed')->group(function () {
        Route::get('/sistema', [DashboardController::class, 'index'])->name('dashboard');

        // Perfil: cambio de contraseña con el cifrado propio del proyecto
        Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/perfil/clave', [ProfileController::class, 'updatePassword'])->name('profile.password');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware(['can:view-admin-records', 'framed'])->group(function () {
            Route::get('/usuarios', [UserAdminController::class, 'index'])->name('users.index');
            Route::get('/auditoria', [AuditController::class, 'index'])->name('audit.index');
        });

        Route::get('/hashes', [AuditController::class, 'hashes'])
            ->middleware(['can:view-password-hashes', 'framed'])->name('hashes.index');

        Route::middleware('can:manage-users')->group(function () {
            Route::post('/usuarios', [UserAdminController::class, 'store'])->name('users.store');
            Route::patch('/usuarios/{user}', [UserAdminController::class, 'update'])->name('users.update');
            Route::patch('/usuarios/{user}/nivel', [UserAdminController::class, 'updateLevel'])->name('users.level');
            Route::patch('/usuarios/{user}/desactivar', [UserAdminController::class, 'deactivate'])->name('users.deactivate');
            Route::post('/usuarios/{user}/clave-temporal', [UserAdminController::class, 'tempPassword'])->name('users.temp');
        });
    });
});
