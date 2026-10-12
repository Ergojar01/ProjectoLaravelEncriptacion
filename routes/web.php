<?php
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Ruta de inicio
Route::get('/', function () {
    return view('welcome');
});

// Rutas protegidas por autenticación
Route::middleware('auth')->group(function () {
    
    // 1. Ruta general del Dashboard (Redirige según el rol en el controlador)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 2. Ejemplo de ruta estrictamente protegida solo para Nivel 500 (Admin)
    Route::get('/configuracion', function () {
        return "Panel de Configuración de Servidores (Solo Nivel 500)";
    })->middleware('role:500')->name('config');

    // 3. Rutas de Perfil por defecto de Breeze
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
