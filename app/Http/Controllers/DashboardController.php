<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Dependiendo del nivel, cargamos una vista Blade diferente
        if ($user->role_level >= 500) {
            return view('dashboard.admin');
        } elseif ($user->role_level >= 499) {
            return view('dashboard.viewer');
        } else {
            return view('dashboard.basic');
        }
    }
}