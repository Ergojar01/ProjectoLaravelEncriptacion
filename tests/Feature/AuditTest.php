<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesUsers;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase, MakesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSecurity();
    }

    public function test_login_fallido_bloqueo_e_intento_bloqueado_quedan_registrados(): void
    {
        $this->makeUser(300, 'a@test.com');

        for ($i = 0; $i < 3; $i++) {
            $this->post('/acceder', ['email' => 'a@test.com', 'password' => 'mala']);
        }
        $this->post('/acceder', ['email' => 'a@test.com', 'password' => self::PASS]);

        $this->assertSame(3, AuditLog::where('event', 'login')->where('result', 'failure')->count());
        $this->assertSame(1, AuditLog::where('event', 'account_locked')->count());
        $this->assertSame(1, AuditLog::where('event', 'login_blocked')->count());
        $this->assertSame(0, AuditLog::where('event', 'login')->where('result', 'success')->count());
    }

    public function test_login_correcto_y_logout_registrados(): void
    {
        $u = $this->makeUser(300, 'b@test.com');

        $this->post('/acceder', ['email' => 'b@test.com', 'password' => self::PASS])->assertRedirect(route('mask'));
        $this->post('/salir');

        $this->assertDatabaseHas('audit_logs', ['event' => 'login', 'result' => 'success', 'user_id' => $u->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'logout', 'result' => 'success', 'user_id' => $u->id]);
    }

    public function test_300_no_ve_la_bitacora_y_el_intento_queda_registrado(): void
    {
        $u = $this->makeUser(300, 'c@test.com');

        $this->actingAs($u)->get('/admin/auditoria')->assertForbidden();

        $this->assertDatabaseHas('audit_logs', ['event' => 'access_denied', 'result' => 'denied', 'user_id' => $u->id]);
    }

    public function test_499_y_500_ven_la_bitacora_con_filtros(): void
    {
        $viewer = $this->makeUser(499, 'v@test.com');
        $admin  = $this->makeUser(500, 'adm@test.com');
        $this->post('/acceder', ['email' => 'zzz@test.com', 'password' => 'x']);

        $this->actingAs($viewer)->get('/admin/auditoria')->assertOk()->assertSee('zzz@test.com');
        $this->actingAs($admin)->get('/admin/auditoria?event=login&result=failure&q=zzz')->assertOk()->assertSee('zzz@test.com');
        $this->actingAs($admin)->get('/admin/auditoria?event=login&result=success')->assertOk()->assertDontSee('zzz@test.com');
        $this->actingAs($admin)->get('/admin/auditoria?event=inventado')->assertSessionHasErrors('event');
    }

    public function test_acciones_de_admin_y_cambio_de_clave_se_registran_sin_contrasenas(): void
    {
        $admin = $this->makeUser(500, 'root@test.com');
        $new   = 'Zx9!kLm2#pQrWv';

        $this->actingAs($admin)->post('/admin/usuarios', [
            'name' => 'Nuevo', 'email' => 'nuevo@test.com', 'password' => $new, 'password_confirmation' => $new, 'role_level' => 300,
        ])->assertRedirect();
        $u = User::where('email', 'nuevo@test.com')->firstOrFail();

        $this->actingAs($admin)->patch("/admin/usuarios/{$u->id}/nivel", ['role_level' => 499]);
        $this->actingAs($admin)->patch("/admin/usuarios/{$u->id}", ['name' => 'Nuevo 2', 'email' => 'nuevo@test.com']);
        $this->actingAs($admin)->post("/admin/usuarios/{$u->id}/restablecer");
        $this->actingAs($admin)->patch("/admin/usuarios/{$u->id}/estado");

        $this->actingAs($admin)->put('/perfil/clave', ['current_password' => 'Equivocada#77x', 'password' => 'Nueva#Clave2026xy', 'password_confirmation' => 'Nueva#Clave2026xy']);
        $this->actingAs($admin)->put('/perfil/clave', ['current_password' => self::PASS, 'password' => 'Nueva#Clave2026xy', 'password_confirmation' => 'Nueva#Clave2026xy']);

        foreach (['user_created', 'level_changed', 'user_updated', 'reset_authorized', 'user_deactivated'] as $ev) {
            $this->assertDatabaseHas('audit_logs', ['event' => $ev, 'result' => 'success', 'user_id' => $admin->id]);
        }
        $this->assertDatabaseHas('audit_logs', ['event' => 'password_changed', 'result' => 'failure']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'password_changed', 'result' => 'success']);

        $all = AuditLog::all()->map(fn ($l) => json_encode($l->toArray()))->implode(' ');
        foreach ([$new, 'Nueva#Clave2026xy', self::PASS, 'Equivocada#77x'] as $secret) {
            $this->assertStringNotContainsString($secret, $all);
        }
        // El token del enlace tampoco aparece
        $token = session('reset_link');
        $this->assertStringNotContainsString('restablecer/', $all);
    }
}
