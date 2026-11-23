<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasswordResetController;
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

// 2. Portal: la única URL visible (/Dashboard). Todo lo demás vive dentro del iframe
//    y cada solicitud se valida igualmente en el servidor (auth + active + role).
Route::get('/Dashboard', fn () => view('mask'))->name('mask')->middleware(['auth', 'active', 'auth.session']);

Route::middleware('guest')->group(function () {
    Route::post('/acceder', [SecureAuthController::class, 'login'])->name('login.store');

    // Recuperación: la persona solicita, el administrador autoriza, la persona define su clave con un enlace de un solo uso
    Route::view('/recuperar-clave', 'auth.recover')->name('password.recover');
    Route::post('/recuperar-clave', [SecureAuthController::class, 'requestReset'])->middleware('throttle:5,10')->name('password.recover.store');
    Route::get('/restablecer/{token}', [PasswordResetController::class, 'show'])->middleware('throttle:20,10')->name('password.reset');
    Route::post('/restablecer/{token}', [PasswordResetController::class, 'update'])->middleware('throttle:10,10')->name('password.reset.store');
});

Route::middleware(['auth', 'active', 'auth.session'])->group(function () {
    Route::post('/salir', [SecureAuthController::class, 'logout'])->name('logout');

    // Pantallas internas: solo se muestran dentro del portal ('framed')
    Route::middleware('framed')->group(function () {
        Route::get('/sistema', [DashboardController::class, 'index'])->name('dashboard');

        // Perfil: cualquier nivel cambia SU contraseña
        Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/perfil/clave', [ProfileController::class, 'updatePassword'])->name('profile.password');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        // 499 y 500: consulta
        Route::middleware(['role:499', 'framed'])->group(function () {
            Route::get('/usuarios', [UserAdminController::class, 'index'])->name('users.index');
            Route::get('/auditoria', [AuditController::class, 'index'])->name('audit.index');
        });
        // Solo 500: administración
        Route::middleware('role:500')->group(function () {
            Route::post('/usuarios', [UserAdminController::class, 'store'])->name('users.store');
            Route::patch('/usuarios/{user}', [UserAdminController::class, 'update'])->name('users.update');
            Route::patch('/usuarios/{user}/nivel', [UserAdminController::class, 'updateLevel'])->name('users.level');
            Route::patch('/usuarios/{user}/estado', [UserAdminController::class, 'toggleStatus'])->name('users.status');
            Route::post('/usuarios/{user}/restablecer', [UserAdminController::class, 'authorizeReset'])->name('users.reset');
            Route::get('/usuarios/{user}/hash', [UserAdminController::class, 'hashForm'])->middleware('framed')->name('users.hash.form');
            Route::post('/usuarios/{user}/hash', [UserAdminController::class, 'hashShow'])->name('users.hash.show');
        });
    });
});
