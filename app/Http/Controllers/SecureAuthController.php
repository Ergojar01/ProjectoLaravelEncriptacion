<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CredentialHasher;
use App\Services\LoginAttempts;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SecureAuthController extends Controller
{
    public function register(Request $request)
    {
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => PasswordPolicy::rules(),
        ], PasswordPolicy::messages());

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            ...CredentialHasher::attributes($data['password']),
            'role_level' => 300,
        ]);

        Auth::login($user);
        $this->startSession($request, $user);

        return redirect()->route('mask');
    }

    public function login(Request $request, LoginAttempts $attempts)
    {
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ], PasswordPolicy::messages());

        $user = User::where('email', $credentials['email'])->whereNull('deactivated_at')->first();
        $password = $credentials['password'];
        $temporary = $user?->must_change_password;
        $attempt = $attempts->verify($credentials['email'], $user, $request->ip(), function () use ($user, $password, $temporary): bool {
            if ($user === null) {
                Hash::make($password);

                return false;
            }

            return $temporary ? CredentialHasher::checkTemporary($user, $password) : CredentialHasher::check($user, $password);
        });

        if ($attempt['seconds'] > 0) {
            $seconds = $attempt['seconds'];
            $message = $attempt['failures'] === 3
                ? 'Fallaste 3 veces. Cuenta bloqueada durante 3 minutos.'
                : "Cuenta bloqueada por intentos fallidos. Intenta de nuevo en {$seconds} s.";

            return back()->withErrors(['email' => $message])->with('lock_seconds', $seconds)->onlyInput('email');
        }

        if ($attempt['failures'] > 0) {
            $left = 3 - $attempt['failures'];

            return back()->withErrors(['email' => "Credenciales incorrectas. Te quedan {$left} intento(s)."])->onlyInput('email');
        }

        if ($temporary) {
            $claimed = DB::transaction(function () use ($user): int {
                $usedAt = now();
                $updated = $user->credentialQuery()
                    ->whereNull('temp_password_used_at')
                    ->where('temp_password_expires_at', '>', $usedAt)
                    ->update(['temp_password_used_at' => $usedAt]);
                if ($updated) {
                    DB::table('password_resets')->where('user_id', $user->id)->update(['used_at' => $usedAt]);
                }

                return $updated;
            });

            if (! $claimed) {
                return back()
                    ->withErrors(['email' => 'La clave temporal ya fue usada o venció. Pide una nueva al administrador.'])
                    ->onlyInput('email');
            }
        } elseif ($user->crypto_version === 1 || Hash::needsRehash($user->password)) {
            $attributes = CredentialHasher::attributes($password);

            if (! $user->credentialQuery()->update($attributes)) {
                return back()->withErrors(['email' => 'La credencial cambió. Vuelve a iniciar sesión.'])->onlyInput('email');
            }

            $user->forceFill($attributes);
        }

        Auth::login($user, ! $temporary && $request->boolean('remember'));
        $this->startSession($request, $user);

        if ($temporary) {
            $request->session()->put('temporary_password_proof', CredentialHasher::temporarySessionProof($user));
        }

        return redirect()->route('mask');
    }

    private function startSession(Request $request, User $user): void
    {
        $request->session()->regenerate();
        $request->session()->put(
            'password_hash_'.Auth::getDefaultDriver(),
            Auth::guard()->hashPasswordForCookie($user->password),
        );
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Una persona 300 o 499 pide una clave temporal: queda marcada para el administrador.
     * La respuesta es siempre la misma, exista o no el correo.
     */
    public function requestTemp(Request $request)
    {
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], PasswordPolicy::messages());

        DB::transaction(function () use ($data): void {
            $user = User::where('email', $data['email'])
                ->whereIn('role_level', [300, 499])
                ->whereNull('deactivated_at')
                ->lockForUpdate()
                ->first();
            if ($user !== null) {
                $requestedAt = now();
                $user->forceFill(['temp_requested_at' => $requestedAt])->save();
                DB::table('password_resets')->upsert([
                    'user_id' => $user->id,
                    'requested_at' => $requestedAt,
                ], ['user_id'], ['requested_at']);
            }
        });

        return back()->with('status', 'Si el correo pertenece a una cuenta de nivel 300 o 499, el administrador ya vio tu solicitud. Él te entregará una clave temporal.');
    }
}
