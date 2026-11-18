<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CredentialHasher;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password'   => PasswordPolicy::rules(),
            'role_level' => ['required', Rule::in([300, 499, 500])],
        ], PasswordPolicy::messages());

        User::create([
            'name'           => $data['name'],
            'email'          => $data['email'],
            ...CredentialHasher::attributes($data['password']),
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

    public function tempPassword(Request $request, User $user)
    {
        $admin = $request->user();

        if ($user->id === $admin->id || $user->role_level >= 500) {
            return back()->with('error', 'Solo se puede asignar una clave temporal a usuarios de nivel 300 o 499.');
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => PasswordPolicy::rules(),
        ], PasswordPolicy::messages());

        $key = 'temp-password:'.$admin->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages(['current_password' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos."]);
        }

        if (! CredentialHasher::check($admin, $data['current_password'])) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages(['current_password' => 'La contraseña actual del administrador no es correcta.']);
        }

        RateLimiter::clear($key);
        if (hash_equals($data['current_password'], $data['password'])
            || CredentialHasher::check($user, $data['password'])
            || CredentialHasher::checkTemporary($user, $data['password'])) {
            throw ValidationException::withMessages(['password' => 'La clave temporal debe ser distinta de las credenciales actuales.']);
        }

        $updated = $user->credentialQuery()->where('role_level', '<', 500)->update([
            'temp_password_hash' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
            'must_change_password' => true,
            'temp_password_expires_at' => now()->addHours(24),
            'temp_password_used_at' => null,
            'temp_requested_at' => null,
        ]);

        if (! $updated) {
            return back()->with('error', 'La cuenta cambió. Vuelve a intentar la operación.');
        }

        $user->revokeDatabaseSessions();

        RateLimiter::clear('login:'.$user->email);
        RateLimiter::clear('lock:'.$user->email);

        return back()->with('status', "Clave temporal asignada a {$user->name}.");
    }
}
