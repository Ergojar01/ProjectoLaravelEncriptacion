<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Los tres niveles (500, 499 y 300) ven exactamente la misma pantalla de
     * bienvenida. Los permisos se siguen aplicando en el servidor (middleware role).
     */
    public function index()
    {
        return view('dashboard.home', ['user' => Auth::user()]);
    }
}
