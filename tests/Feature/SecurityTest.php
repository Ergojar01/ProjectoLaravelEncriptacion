<?php

namespace Tests\Feature;

use App\Models\AccountLock;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\Credentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MakesUsers;
use Tests\TestCase;

/** Las pruebas obligatorias del PDF (sección 10) y los controles asociados. */
class SecurityTest extends TestCase
{
    use MakesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSecurity();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // --- Hash -----------------------------------------------------------------
    public function test_la_contrasena_se_guarda_con_argon2id_y_sal_unica(): void
    {
        $a = $this->makeUser(300, 'a@test.com');
        $b = $this->makeUser(300, 'b@test.com');

        $this->assertStringStartsWith('$argon2id$', $a->password);
        $this->assertNotSame($a->password, $b->password);              // misma clave, sal distinta
        $this->assertStringNotContainsString(self::PASS, $a->password);
        $this->assertSame(2, (int) $a->crypto_version);
    }

    public function test_cuenta_antigua_v1_entra_y_migra_a_argon2id(): void
    {
        $u = $this->makeUser(300, 'old@test.com', legacy: true);
        $this->assertSame(1, (int) $u->crypto_version);

        $this->post('/acceder', ['email' => 'old@test.com', 'password' => self::PASS])->assertRedirect(route('mask'));

        $u->refresh();
        $this->assertSame(2, (int) $u->crypto_version);
        $this->assertStringStartsWith('$argon2id$', $u->password);
        $this->assertNull($u->salt);
    }

    // --- 1. Contraseña incorrecta ---------------------------------------------
    public function test_previous_argon2id_credentials_upgrade_after_login(): void
    {
        $user = $this->makeUser(300, 'legacy-argon@haki.test');
        $original = Hash::make(self::PASS);
        $user->forceFill(['password' => $original, 'salt' => str_repeat('a', 64)])->save();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => self::PASS])
            ->assertRedirect(route('mask'));

        $updated = $user->fresh();
        $this->assertNull($updated->salt);
        $this->assertNotSame($original, $updated->password);
        $this->assertTrue(Credentials::check($updated, self::PASS));
    }

    public function test_contrasena_incorrecta_es_rechazada_con_mensaje_generico(): void
    {
        $this->makeUser(300, 'a@test.com');

        $existe = $this->post('/acceder', ['email' => 'a@test.com', 'password' => 'mala']);
        $noExiste = $this->post('/acceder', ['email' => 'nadie@test.com', 'password' => 'mala']);

        $existe->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(session('errors')->first('email'), session('errors')->first('email'));
        $this->assertStringContainsString('Credenciales incorrectas', $existe->getSession()->get('errors')->first('email'));
        $this->assertStringContainsString('Credenciales incorrectas', $noExiste->getSession()->get('errors')->first('email'));
    }

    // --- 2, 3, 4. Bloqueo -----------------------------------------------------
    public function test_tres_fallos_bloquean_3_minutos_y_el_bloqueo_sobrevive_a_sesion_nueva(): void
    {
        $this->makeUser(300, 'a@test.com');
        for ($i = 0; $i < 3; $i++) {
            $this->post('/acceder', ['email' => 'a@test.com', 'password' => 'mala']);
        }

        $this->assertSame(1, AccountLock::count());
        $this->assertSame(180, (int) config('security.login.lock_seconds'));

        // Sesión nueva (cookies y sesión vacías): con la clave CORRECTA sigue bloqueado
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $r = $this->post('/acceder', ['email' => 'a@test.com', 'password' => self::PASS]);
        $r->assertSessionHasErrors('email');
        $this->assertGuest();

        // El bloqueo vive en MySQL: limpiar la caché no lo levanta
        $this->artisan('cache:clear');
        $this->post('/acceder', ['email' => 'a@test.com', 'password' => self::PASS]);
        $this->assertGuest();
    }

    public function test_pasados_3_minutos_la_clave_correcta_entra_y_reinicia_contador(): void
    {
        $this->makeUser(300, 'a@test.com');
        for ($i = 0; $i < 3; $i++) {
            $this->post('/acceder', ['email' => 'a@test.com', 'password' => 'mala']);
        }

        Carbon::setTestNow(now()->addSeconds(181));
        $this->post('/acceder', ['email' => 'a@test.com', 'password' => self::PASS])->assertRedirect(route('mask'));
        $this->assertAuthenticated();
        $this->assertSame(0, LoginAttempt::whereNull('cleared_at')->count());
    }

    public function test_un_atacante_en_otra_ip_no_bloquea_la_cuenta_con_3_intentos(): void
    {
        $this->makeUser(300, 'a@test.com');
        for ($i = 0; $i < 3; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->post('/acceder', ['email' => 'a@test.com', 'password' => 'mala']);
        }
        // La persona legítima, desde su IP, entra con normalidad
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->post('/acceder', ['email' => 'a@test.com', 'password' => self::PASS])->assertRedirect(route('mask'));
    }

    public function test_limite_global_por_origen_y_por_cuenta_distribuida(): void
    {
        config(['security.login.ip_max' => 5]);
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->post('/acceder', ['email' => "x{$i}@test.com", 'password' => 'mala']);
        }
        $this->assertTrue(AccountLock::where('scope', 'ip')->exists());

        config(['security.login.account_max' => 4, 'security.login.ip_max' => 99]);
        for ($i = 0; $i < 4; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "192.0.2.{$i}"])->post('/acceder', ['email' => 'victima@test.com', 'password' => 'mala']);
        }
        $this->assertTrue(AccountLock::where('scope', 'account')->exists());
    }

    // --- 5, 6, 7. Autorización ------------------------------------------------
    public function test_300_no_entra_a_administracion_499_no_crea_y_300_no_toca_a_otros(): void
    {
        $basic = $this->makeUser(300, 'b@test.com');
        $viewer = $this->makeUser(499, 'v@test.com');
        $other = $this->makeUser(300, 'o@test.com');

        $this->actingAs($basic)->get('/admin/usuarios', ['Sec-Fetch-Dest' => 'document'])->assertForbidden();
        $this->actingAs($basic)->get('/admin/auditoria')->assertForbidden();
        $this->actingAs($basic)->patch("/admin/usuarios/{$other->id}/nivel", ['role_level' => 499])->assertForbidden();
        $this->actingAs($basic)->patch("/admin/usuarios/{$other->id}/estado")->assertForbidden();
        $this->actingAs($basic)->post("/admin/usuarios/{$other->id}/restablecer")->assertForbidden();
        $this->actingAs($basic)->get("/admin/usuarios/{$other->id}/hash")->assertForbidden();

        $this->flushSession();
        $this->actingAs($viewer)->post('/admin/usuarios', ['name' => 'X', 'email' => 'x@test.com', 'password' => 'Zx9!kLm2#pQrWv', 'password_confirmation' => 'Zx9!kLm2#pQrWv', 'role_level' => 300])->assertForbidden();
        $this->actingAs($viewer)->patch("/admin/usuarios/{$other->id}", ['name' => 'H', 'email' => 'h@test.com'])->assertForbidden();
        $this->actingAs($viewer)->get("/admin/usuarios/{$other->id}/hash")->assertForbidden();

        $this->assertSame(300, (int) $other->fresh()->role_level);
        $this->assertDatabaseMissing('users', ['email' => 'x@test.com']);
    }

    public function test_499_ve_usuarios_y_estados_pero_nunca_hashes(): void
    {
        $viewer = $this->makeUser(499, 'v@test.com');
        $u = $this->makeUser(300, 'o@test.com');

        $this->actingAs($viewer)->get('/admin/usuarios')->assertOk()->assertSee('o@test.com')->assertDontSee(substr($u->password, 0, 20));
    }

    // --- 8, 9, 10. Inyección, XSS, CSRF ---------------------------------------
    public function test_caracteres_sql_no_alteran_la_autenticacion(): void
    {
        $this->makeUser(300, 'a@test.com');
        $this->post('/acceder', ['email' => "' OR '1'='1' --", 'password' => "' OR 1=1 --"]);
        $this->post('/acceder', ['email' => 'a@test.com', 'password' => "x' OR '1'='1"]);
        $this->assertGuest();
        $this->assertSame(2, User::count() + 1);   // la tabla sigue intacta (1 usuario)
    }

    public function test_html_y_javascript_se_escapan(): void
    {
        $admin = $this->makeUser(500, 'adm@test.com');
        $evil = '<script>alert(1)</script>';
        $this->actingAs($admin)->post('/admin/usuarios', ['name' => $evil, 'email' => 'e@test.com', 'password' => 'Zx9!kLm2#pQrWv', 'password_confirmation' => 'Zx9!kLm2#pQrWv', 'role_level' => 300]);

        $this->actingAs($admin)->get('/admin/usuarios')->assertOk()->assertDontSee($evil, false)->assertSee('&lt;script&gt;', false);
        $this->actingAs($admin)->get('/admin/auditoria')->assertOk()->assertDontSee($evil, false);
    }

    public function test_solicitud_sin_token_csrf_es_rechazada(): void
    {
        $admin = $this->makeUser(500, 'adm@test.com');
        $this->app['env'] = 'production';           // en 'testing' Laravel desactiva el CSRF

        $this->actingAs($admin)->post('/admin/usuarios', ['name' => 'X', 'email' => 'x@test.com'], ['Sec-Fetch-Site' => 'same-origin'])->assertStatus(419);
        $this->post('/acceder', ['email' => 'a@test.com', 'password' => 'x'])->assertStatus(419);
    }

    // --- 11. Sesión -----------------------------------------------------------
    public function test_cerrar_sesion_invalida_la_sesion(): void
    {
        config(['session.driver' => 'database', 'session.encrypt' => true]);
        $user = $this->makeUser(300, 'a@test.com');
        $cookie = config('session.cookie');
        $old = $this->post('/acceder', ['email' => $user->email, 'password' => self::PASS])
            ->assertRedirect(route('mask'))->getCookie($cookie)->getValue();
        $csrf = session()->token();
        $this->assertDatabaseHas('sessions', ['id' => $old, 'user_id' => $user->id]);

        $this->withCookie($cookie, $old)->post('/salir')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNotSame($csrf, session()->token());
        $this->assertDatabaseMissing('sessions', ['id' => $old]);

        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->withCookie($cookie, $old)->get('/Dashboard')->assertRedirect(route('login'));
    }

    public function test_cambio_de_clave_exige_12_y_actual_actualiza_hash_e_invalida_otras_sesiones(): void
    {
        $u = $this->makeUser(300, 'a@test.com');
        $before = $u->password;
        foreach (['otra1', 'otra2'] as $id) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $u->id, 'payload' => 'x', 'last_activity' => time()]);
        }

        $this->actingAs($u)->put('/perfil/clave', ['current_password' => self::PASS, 'password' => 'Corta#1a', 'password_confirmation' => 'Corta#1a'])->assertSessionHasErrors('password');
        $this->actingAs($u)->put('/perfil/clave', ['current_password' => 'incorrecta', 'password' => 'Nueva#Clave2026xy', 'password_confirmation' => 'Nueva#Clave2026xy'])->assertSessionHasErrors('current_password');
        $this->assertSame($before, $u->fresh()->password);

        $this->actingAs($u)->put('/perfil/clave', ['current_password' => self::PASS, 'password' => 'Nueva#Clave2026xy', 'password_confirmation' => 'Nueva#Clave2026xy'])->assertSessionHasNoErrors();

        $this->assertNotSame($before, $u->fresh()->password);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $u->id)->whereIn('id', ['otra1', 'otra2'])->count());
    }

    public function test_politica_rechaza_contrasenas_comunes_y_con_el_usuario(): void
    {
        $u = $this->makeUser(300, 'maria.lopez@test.com');
        foreach (['Password123!!', 'Qwerty123456!', 'Maria.Lopez#2026'] as $weak) {
            $this->actingAs($u)->put('/perfil/clave', ['current_password' => self::PASS, 'password' => $weak, 'password_confirmation' => $weak])->assertSessionHasErrors('password');
        }
        $long = 'Frase larga y segura: el barco navega al amanecer 2026!';
        $this->actingAs($u)->put('/perfil/clave', ['current_password' => self::PASS, 'password' => $long, 'password_confirmation' => $long])->assertSessionHasNoErrors();
    }

    // --- Autorregistro opcional -----------------------------------------------
    public function test_registration_is_disabled_by_default(): void
    {
        $this->get('/registro')->assertNotFound();
        $this->post('/registro', ['name' => 'X', 'email' => 'x@test.com'])->assertStatus(404);
    }

    public function test_registration_uses_the_password_policy_and_creates_only_basic_users(): void
    {
        config(['security.registration.enabled' => true]);
        $this->get(route('login'))->assertSee('Crear cuenta');
        $this->get(route('register'))->assertOk()
            ->assertSee('data-pass-rules="password"', false)
            ->assertSee('data-rule="match"', false);
        $data = ['name' => 'Cuenta de prueba', 'email' => 'PRUEBA@haki.test', 'role_level' => 500];
        foreach (['Ab1!', 'Password123!!', 'Prueba#Segura987!', 'LunaSinNumeros!'] as $password) {
            $this->post(route('register.store'), $data + [
                'password' => $password, 'password_confirmation' => $password,
            ])->assertSessionHasErrors('password');
        }
        $password = 'Marea#Nautica-2026!';
        $this->post(route('register.store'), $data + [
            'password' => $password, 'password_confirmation' => $password,
        ])->assertRedirect(route('login'))->assertSessionHasNoErrors();

        $user = User::where('email', 'prueba@haki.test')->sole();
        $this->assertSame(300, $user->role_level);
        $this->assertTrue(Credentials::check($user, $password));
        $this->assertDatabaseHas('audit_logs', ['event' => 'user_registered', 'user_id' => $user->id]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => $password])->assertRedirect(route('mask'));
    }

    // --- Desactivar sin borrar ------------------------------------------------
    public function test_desactivar_cuenta_bloquea_acceso_y_conserva_historial(): void
    {
        $admin = $this->makeUser(500, 'adm@test.com');
        $u = $this->makeUser(300, 'a@test.com');
        $this->post('/acceder', ['email' => 'a@test.com', 'password' => self::PASS]);
        $this->post('/salir');

        $this->actingAs($admin)->patch("/admin/usuarios/{$u->id}/estado")->assertSessionHas('status');
        $this->assertFalse($u->fresh()->is_active);
        $this->assertDatabaseHas('users', ['id' => $u->id]);

        auth()->logout();
        $this->post('/acceder', ['email' => 'a@test.com', 'password' => self::PASS])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($admin)->patch("/admin/usuarios/{$u->id}/estado");
        $this->assertTrue($u->fresh()->is_active);
    }

    public function test_no_se_puede_desactivar_al_ultimo_admin_ni_asignar_500(): void
    {
        $admin = $this->makeUser(500, 'adm@test.com');
        $u = $this->makeUser(300, 'a@test.com');

        $this->actingAs($admin)->patch("/admin/usuarios/{$admin->id}/estado")->assertSessionHas('error');
        $this->actingAs($admin)->patch("/admin/usuarios/{$u->id}/nivel", ['role_level' => 500])->assertSessionHasErrors('role_level');
        $this->actingAs($admin)->post('/admin/usuarios', ['name' => 'X', 'email' => 'x@test.com', 'password' => 'Zx9!kLm2#pQrWv', 'password_confirmation' => 'Zx9!kLm2#pQrWv', 'role_level' => 500])->assertSessionHasErrors('role_level');
    }

    // --- Edición de datos -----------------------------------------------------
    public function test_500_modifica_datos_de_otro_usuario(): void
    {
        $admin = $this->makeUser(500, 'adm@test.com');
        $u = $this->makeUser(300, 'a@test.com');

        $this->actingAs($admin)->patch("/admin/usuarios/{$u->id}", ['name' => 'Nombre Nuevo', 'email' => 'nuevo@test.com'])->assertSessionHas('status');
        $this->assertSame('nuevo@test.com', $u->fresh()->email);

        $old = $this->makeUser(300, 'v1@test.com', legacy: true);
        $this->actingAs($admin)->patch("/admin/usuarios/{$old->id}", ['name' => 'V1', 'email' => 'otro@test.com'])->assertSessionHasErrors('email');
    }

    // --- Hash visible solo para 500, con clave y auditado ----------------------
    public function test_500_ve_el_hash_solo_con_su_clave_y_queda_auditado(): void
    {
        $admin = $this->makeUser(500, 'adm@test.com');
        $u = $this->makeUser(300, 'a@test.com');

        $this->actingAs($admin)->post("/admin/usuarios/{$u->id}/hash", ['admin_password' => 'mala'])->assertSessionHasErrors('admin_password');
        $this->assertDatabaseHas('audit_logs', ['event' => 'hash_viewed', 'result' => 'denied']);

        $this->actingAs($admin)->post("/admin/usuarios/{$u->id}/hash", ['admin_password' => self::PASS])
            ->assertOk()->assertSee($u->password, false)->assertHeader('Cache-Control');
        $this->assertDatabaseHas('audit_logs', ['event' => 'hash_viewed', 'result' => 'success', 'target_user_id' => $u->id]);
    }

    public function test_hash_is_withheld_if_the_audit_write_fails(): void
    {
        $admin = $this->makeUser(500, 'adm@test.com');
        $user = $this->makeUser(300, 'a@test.com');

        AuditLog::creating(fn () => throw new \RuntimeException('Auditoría temporalmente indisponible'));

        $this->actingAs($admin)->post(route('admin.users.hash.show', $user), ['admin_password' => self::PASS])
            ->assertStatus(503)->assertDontSee($user->password, false);
    }

    public function test_a_new_password_invalidates_a_pending_reset_link(): void
    {
        $admin = $this->makeUser(500, 'adm@test.com');
        $user = $this->makeUser(300, 'a@test.com');
        $this->actingAs($admin)->post(route('admin.users.reset', $user))->assertSessionHas('reset_link');
        $link = session('reset_link');

        $this->flushSession();
        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => self::PASS,
            'password' => 'Marca#Nautica-2026!',
            'password_confirmation' => 'Marca#Nautica-2026!',
        ])->assertSessionHasNoErrors();

        auth()->logout();
        $this->get($link)->assertStatus(410);
    }

    // --- Restablecimiento con enlace de un solo uso ----------------------------
    public function test_restablecimiento_enlace_unico_con_vencimiento(): void
    {
        $admin = $this->makeUser(500, 'adm@test.com');
        $u = $this->makeUser(300, 'a@test.com');
        $this->post('/recuperar-clave', ['email' => 'a@test.com']);
        $this->assertNotNull($u->fresh()->temp_requested_at);

        $this->actingAs($admin)->post("/admin/usuarios/{$u->id}/restablecer")->assertSessionHas('reset_link');
        $link = session('reset_link');
        $token = basename($link);
        auth()->logout();

        // Solo se guarda el hash del token
        $this->assertDatabaseMissing('password_resets', ['token_hash' => $token]);
        $this->assertDatabaseHas('password_resets', ['token_hash' => hash('sha256', $token)]);

        $this->get("/restablecer/{$token}")->assertOk()->assertSee('Crea tu contraseña nueva');
        $this->post("/restablecer/{$token}", ['password' => 'Corta#1', 'password_confirmation' => 'Corta#1'])->assertSessionHasErrors('password');

        $new = 'Mi#Nueva-Clave2026!';
        $this->post("/restablecer/{$token}", ['password' => $new, 'password_confirmation' => $new])->assertRedirect(route('login'));

        // Un solo uso
        $this->get("/restablecer/{$token}")->assertStatus(410);
        $this->post("/restablecer/{$token}", ['password' => $new, 'password_confirmation' => $new])->assertStatus(410);

        $this->post('/acceder', ['email' => 'a@test.com', 'password' => $new])->assertRedirect(route('mask'));
    }

    public function test_enlace_vencido_o_invalido_es_rechazado(): void
    {
        $admin = $this->makeUser(500, 'adm@test.com');
        $u = $this->makeUser(300, 'a@test.com');
        $this->actingAs($admin)->post("/admin/usuarios/{$u->id}/restablecer");
        $token = basename(session('reset_link'));
        auth()->logout();

        $this->get('/restablecer/'.str_repeat('a', 64))->assertStatus(410);
        $this->get('/restablecer/no-es-un-token')->assertStatus(410);

        Carbon::setTestNow(now()->addMinutes(31));
        $this->get("/restablecer/{$token}")->assertStatus(410);
    }

    public function test_el_admin_no_puede_autorizar_a_otro_500_ni_a_si_mismo(): void
    {
        $admin = $this->makeUser(500, 'adm@test.com');
        $this->actingAs($admin)->post("/admin/usuarios/{$admin->id}/restablecer")->assertSessionHas('error');
        $this->assertSame(0, PasswordReset::count());
    }

    public function test_solicitud_de_recuperacion_no_revela_si_el_correo_existe(): void
    {
        $this->makeUser(300, 'a@test.com');
        $a = $this->post('/recuperar-clave', ['email' => 'a@test.com'])->getSession()->get('status');
        $b = $this->post('/recuperar-clave', ['email' => 'nadie@test.com'])->getSession()->get('status');
        $this->assertSame($a, $b);
    }

    public function test_cabeceras_de_seguridad(): void
    {
        $r = $this->get('/');
        $r->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertStringContainsString("script-src 'self'", $r->headers->get('Content-Security-Policy'));
    }
}
