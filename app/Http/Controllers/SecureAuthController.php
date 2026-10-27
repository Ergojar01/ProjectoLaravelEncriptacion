<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CredentialHasher;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class SecureAuthController extends Controller
{
    public function register(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordPolicy::rule()],
        ], PasswordPolicy::messages());

        $salt = CredentialHasher::newSalt();

        $user = User::create([
            'name'           => $data['name'],
            'email'          => $data['email'],
            'salt'           => $salt,
            'password'       => CredentialHasher::make($data['email'], $data['password'], $salt),
            'crypto_version' => config('crypto.version'),
            'role_level'     => 300,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('mask');
    }

    public function login(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ], PasswordPolicy::messages());

        $key = 'login:' . $credentials['email'] . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()
                ->withErrors(['email' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos."])
                ->onlyInput('email');
        }

        $user = User::where('email', $credentials['email'])->first();

        $valid = $user
            ? CredentialHasher::check($credentials['email'], $credentials['password'], $user->salt, $user->password)
            : CredentialHasher::burn($credentials['email'], $credentials['password']);

        if (! $valid) {
            RateLimiter::hit($key, 300);

            return back()
                ->withErrors(['email' => 'Credenciales incorrectas.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($key);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route('mask');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
