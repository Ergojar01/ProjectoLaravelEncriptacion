<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CredentialHasher;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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

    /**
     * Genera una clave temporal de un solo uso para usuarios 300 y 499.
     * Se muestra una única vez al administrador; no se guarda en claro.
     */
    public function tempPassword(User $user)
    {
        if ($user->id === Auth::id() || (int) $user->role_level >= 500) {
            return back()->with('error', 'Solo se puede generar clave temporal para usuarios 300 y 499.');
        }

        $temp = $this->generateTemp();
        $salt = CredentialHasher::newSalt();

        $user->forceFill([
            'salt'                     => $salt,
            'password'                 => CredentialHasher::make($user->email, $temp, $salt),
            'crypto_version'           => config('crypto.version'),
            'must_change_password'     => true,
            'temp_password_expires_at' => now()->addHours(24),
            'temp_password_used_at'    => null,
            'temp_requested_at'        => null,
        ])->save();

        // Cierra sesiones activas y levanta cualquier bloqueo de esa cuenta
        DB::table('sessions')->where('user_id', $user->id)->delete();
        RateLimiter::clear('login:' . $user->email);
        RateLimiter::clear('lock:' . $user->email);

        return back()
            ->with('status', "Clave temporal generada para {$user->name}.")
            ->with('temp_password', $temp);
    }

    private function generateTemp(): string
    {
        $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#%*?'];
        $all  = implode('', $sets);

        $out = array_map(fn ($s) => $s[random_int(0, strlen($s) - 1)], $sets);
        while (count($out) < 12) {
            $out[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($out) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$out[$i], $out[$j]] = [$out[$j], $out[$i]];
        }

        return implode('', $out);
    }
}
