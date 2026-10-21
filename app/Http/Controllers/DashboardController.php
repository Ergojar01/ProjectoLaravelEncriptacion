<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user  = Auth::user();
        $level = (int) $user->role_level;
        $data  = ['user' => $user];

        if ($level >= 499) {
            $data['stats'] = [
                'total'   => User::count(),
                'admins'  => User::where('role_level', '>=', 500)->count(),
                'viewers' => User::where('role_level', 499)->count(),
                'basic'   => User::where('role_level', '<', 499)->count(),
            ];
            $data['recent'] = User::latest()->take(5)->get();
        }

        $view = match (true) {
            $level >= 500 => 'dashboard.admin',
            $level >= 499 => 'dashboard.viewer',
            default       => 'dashboard.basic',
        };

        return view($view, $data);
    }
}
