<?php

namespace App\Http\Controllers;

use App\Models\PasswordReset;
use App\Models\User;
use App\Services\Audit;
use App\Services\Credentials;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserAdminController extends Controller
{
    /** Niveles que un administrador puede asignar (el 500 no se asigna). */
    private const ASSIGNABLE = [300, 499];

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
            'password'   => ['required', 'confirmed', ...PasswordPolicy::rules($request->input('email'))],
            'role_level' => ['required', Rule::in(self::ASSIGNABLE)],
        ], PasswordPolicy::messages());

        $created = new User([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'role_level' => (int) $data['role_level'],
            'is_active'  => true,
        ]);
        Credentials::apply($created, $data['password']);
        $created->save();

        Audit::log('user_created', Audit::SUCCESS, Auth::user(), $created->email, $created, 'Nivel asignado: ' . $created->role_level);

        return redirect()->route('admin.users.index')->with('status', 'Usuario registrado correctamente.');
    }

    /** Modifica nombre y correo de otra persona (solo 500). */
    public function update(Request $request, User $user)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], PasswordPolicy::messages());

        $emailChanged = $data['email'] !== $user->email;

        // Las cuentas con el esquema v1 usan el correo como parte del hash: primero deben migrar a Argon2id.
        if ($emailChanged && (int) $user->crypto_version < Credentials::VERSION) {
            return back()->withInput()->withErrors(['email' => 'Esta cuenta aún usa el esquema anterior. Podrás cambiar su correo cuando la persona inicie sesión una vez.']);
        }

        $changes = [];
        if ($data['name'] !== $user->name) {
            $changes[] = "nombre: {$user->name} → {$data['name']}";
        }
        if ($emailChanged) {
            $changes[] = "correo: {$user->email} → {$data['email']}";
        }

        $user->update(['name' => $data['name'], 'email' => $data['email']]);

        if ($emailChanged && $user->id !== Auth::id()) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        Audit::log('user_updated', Audit::SUCCESS, Auth::user(), $user->email, $user, $changes ? implode('; ', $changes) : 'Sin cambios');

        return back()->with('status', "Datos de {$user->name} actualizados.");
    }

    public function updateLevel(Request $request, User $user)
    {
        $data = $request->validate([
            'role_level' => ['required', Rule::in(self::ASSIGNABLE)],
        ], PasswordPolicy::messages());

        if ($user->id === Auth::id()) {
            return back()->with('error', 'No puedes cambiar tu propio nivel de acceso.');
        }
        if ((int) $user->role_level >= 500) {
            return back()->with('error', 'El nivel 500 no se modifica desde el sistema.');
        }

        $old = (int) $user->role_level;
        $user->update(['role_level' => (int) $data['role_level']]);
        DB::table('sessions')->where('user_id', $user->id)->delete();   // el nuevo nivel aplica desde un inicio de sesión limpio

        Audit::log('level_changed', Audit::SUCCESS, Auth::user(), $user->email, $user, "Nivel {$old} → {$user->role_level}");

        return back()->with('status', "Nivel de {$user->name} actualizado.");
    }

    /** Desactiva o reactiva (no se borra nada: se conserva el historial de auditoría). */
    public function toggleStatus(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        if ($user->is_active) {
            $otherAdmins = User::where('role_level', 500)->where('is_active', true)->where('id', '!=', $user->id)->count();
            if ((int) $user->role_level >= 500 && $otherAdmins === 0) {
                return back()->with('error', 'Debe quedar al menos un administrador activo.');
            }

            $user->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            Audit::log('user_deactivated', Audit::SUCCESS, Auth::user(), $user->email, $user, 'Cuenta desactivada; sesiones cerradas');

            return back()->with('status', "Cuenta de {$user->name} desactivada.");
        }

        $user->forceFill(['is_active' => true, 'deactivated_at' => null])->save();
        Audit::log('user_activated', Audit::SUCCESS, Auth::user(), $user->email, $user, 'Cuenta reactivada');

        return back()->with('status', "Cuenta de {$user->name} reactivada.");
    }

    /**
     * Autoriza el restablecimiento: genera un enlace de un solo uso con vencimiento.
     * Solo se guarda el hash del token. El administrador nunca define ni conoce la contraseña.
     */
    public function authorizeReset(User $user)
    {
        if ($user->id === Auth::id() || (int) $user->role_level >= 500 || ! $user->is_active) {
            return back()->with('error', 'Solo se puede autorizar el restablecimiento de cuentas activas de nivel 300 y 499.');
        }

        $ttl   = (int) config('security.reset.ttl_minutes');
        $token = bin2hex(random_bytes(32));

        PasswordReset::where('user_id', $user->id)->whereNull('used_at')->where('expires_at', '>', now())->update(['expires_at' => now()]);
        PasswordReset::create([
            'user_id'    => $user->id,
            'token_hash' => PasswordReset::hashToken($token),
            'expires_at' => now()->addMinutes($ttl),
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);

        $user->forceFill(['temp_requested_at' => null])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        Audit::log('reset_authorized', Audit::SUCCESS, Auth::user(), $user->email, $user, "Enlace de un solo uso, vence en {$ttl} min");

        $link = route('password.reset', ['token' => $token]);

        if (config('security.reset.show_link')) {
            return back()->with('status', "Restablecimiento autorizado para {$user->name}.")->with('reset_link', $link)->with('reset_ttl', $ttl);
        }

        try {
            Mail::raw("Un administrador autorizó restablecer tu contraseña.\nCrea la nueva aquí (un solo uso, vence en {$ttl} minutos):\n{$link}", fn ($m) => $m->to($user->email)->subject('Restablecer contraseña'));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo enviar el correo. Revisa la configuración de MAIL_*.');
        }

        return back()->with('status', "Enlace enviado por correo a {$user->name}.");
    }

    /** Consulta académica del hash: solo lectura, con confirmación de clave y auditada. */
    public function hashForm(User $user)
    {
        return view('admin.hash', ['target' => $user, 'hash' => null]);
    }

    public function hashShow(Request $request, User $user)
    {
        $request->validate(['admin_password' => ['required', 'string', 'max:1024']]);

        $key = 'hashview:' . Auth::id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            Audit::log('hash_viewed', Audit::DENIED, Auth::user(), $user->email, $user, 'Demasiados intentos de confirmación');

            return back()->withErrors(['admin_password' => 'Demasiados intentos. Espera unos minutos.']);
        }

        if (! Credentials::check(Auth::user(), $request->input('admin_password'))) {
            RateLimiter::hit($key, 300);
            Audit::log('hash_viewed', Audit::DENIED, Auth::user(), $user->email, $user, 'Confirmación de contraseña incorrecta');

            return back()->withErrors(['admin_password' => 'Tu contraseña no es correcta.']);
        }

        RateLimiter::clear($key);
        Audit::log('hash_viewed', Audit::SUCCESS, Auth::user(), $user->email, $user, 'Consulta de solo lectura del hash almacenado');

        return response()
            ->view('admin.hash', ['target' => $user, 'hash' => $user->makeVisible('password')->password])
            ->header('Cache-Control', 'no-store, private');
    }
}
