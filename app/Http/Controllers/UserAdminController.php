<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CredentialHasher;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserAdminController extends Controller
{
    public function index()
    {
        return view('admin.users', [
            'users'          => User::orderBy('id')->get(),
            'currentVersion' => config('crypto.version'),
            'canManage'      => Auth::user()->role_level >= 500,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password'   => ['required', 'confirmed', PasswordPolicy::rule()],
            'role_level' => ['required', Rule::in([300, 499, 500])],
        ], PasswordPolicy::messages());

        $salt = CredentialHasher::newSalt();

        User::create([
            'name'           => $data['name'],
            'email'          => $data['email'],
            'salt'           => $salt,
            'password'       => CredentialHasher::make($data['email'], $data['password'], $salt),
            'crypto_version' => config('crypto.version'),
            'role_level'     => (int) $data['role_level'],
        ]);

        return redirect()->route('admin.users.index')->with('status', 'Usuario registrado correctamente.');
    }

    public function updateLevel(Request $request, User $user)
    {
        $data = $request->validate([
            'role_level' => ['required', Rule::in([300, 499, 500])],
        ], PasswordPolicy::messages());

        if ($user->id === Auth::id()) {
            return back()->with('error', 'No puedes cambiar tu propio nivel de acceso.');
        }

        $user->update(['role_level' => (int) $data['role_level']]);

        return back()->with('status', "Nivel de {$user->name} actualizado.");
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $user->delete();

        return back()->with('status', 'Usuario eliminado.');
    }
}
