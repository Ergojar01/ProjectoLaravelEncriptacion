<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CredentialHasher;
use App\Services\LoginAttempts;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserAdminController extends Controller
{
    public function index(Request $request): View
    {
        $canManage = $request->user()->can('manage-users');
        $columns = ['id', 'name', 'email', 'role_level', 'crypto_version', 'deactivated_at'];

        if ($canManage) {
            $columns[] = 'temp_requested_at';
        }

        return view('admin.users', [
            'users' => User::orderBy('id')->get($columns),
            'currentVersion' => config('crypto.version'),
            'canManage' => $canManage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => PasswordPolicy::rules(),
            'role_level' => ['required', Rule::in([300, 499])],
        ], PasswordPolicy::messages());

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            ...CredentialHasher::attributes($data['password']),
            'role_level' => (int) $data['role_level'],
        ]);

        return redirect()->route('admin.users.index')->with('status', 'Usuario registrado correctamente.');
    }

    public function updateLevel(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role_level' => ['required', Rule::in([300, 499])],
        ], PasswordPolicy::messages());

        if ($user->id === Auth::id()) {
            return back()->with('error', 'No puedes cambiar tu propio nivel de acceso.');
        }

        $user->update(['role_level' => (int) $data['role_level']]);

        return back()->with('status', "Nivel de {$user->name} actualizado.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Esta opción solo permite editar otras cuentas.');
        }

        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:255', Rule::unique('users')->ignore($user)],
        ]);

        if (isset($data['email']) && $data['email'] !== $user->email) {
            // El hash heredado incorpora el correo. El próximo acceso válido lo migra.
            if ($user->crypto_version === 1) {
                throw ValidationException::withMessages([
                    'email' => 'El usuario debe iniciar sesión con su contraseña permanente antes de cambiar el correo.',
                ]);
            }

            $data['email_verified_at'] = null;
        }

        $user->forceFill($data)->save();

        return back()->with('status', "Datos de {$user->name} actualizados.");
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        DB::transaction(function () use ($user): void {
            User::whereKey($user->id)->whereNull('deactivated_at')->update([
                'deactivated_at' => now(),
                'remember_token' => Str::random(60),
                'temp_password_hash' => null,
                'temp_password_expires_at' => null,
                'temp_password_used_at' => null,
                'temp_requested_at' => null,
            ]);

            DB::table('password_resets')->where('user_id', $user->id)->delete();
            $user->revokeDatabaseSessions();
        });

        return back()->with('status', 'Cuenta desactivada.');
    }

    public function tempPassword(Request $request, User $user, LoginAttempts $attempts): RedirectResponse
    {
        $admin = $request->user();

        if ($user->deactivated_at !== null || ! in_array($user->role_level, [300, 499], true)) {
            return back()->with('error', 'Solo se puede asignar una clave temporal a cuentas activas de nivel 300 o 499.');
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

        $hash = Hash::make($data['password']);
        $expiresAt = now()->addHours(24);
        $updated = DB::transaction(function () use ($user, $hash, $expiresAt): int {
            $updated = $user->credentialQuery()->whereIn('role_level', [300, 499])->update([
                'temp_password_hash' => $hash,
                'remember_token' => Str::random(60),
                'must_change_password' => true,
                'temp_password_expires_at' => $expiresAt,
                'temp_password_used_at' => null,
                'temp_requested_at' => null,
            ]);
            if ($updated) {
                DB::table('password_resets')->updateOrInsert(['user_id' => $user->id], [
                    'token_hash' => $hash,
                    'expires_at' => $expiresAt,
                    'used_at' => null,
                    'requested_at' => null,
                ]);
            }

            return $updated;
        });

        if (! $updated) {
            return back()->with('error', 'La cuenta cambió. Vuelve a intentar la operación.');
        }

        $user->revokeDatabaseSessions();

        $attempts->clear($user->email);

        return back()->with('status', "Clave temporal asignada a {$user->name}.");
    }
}
