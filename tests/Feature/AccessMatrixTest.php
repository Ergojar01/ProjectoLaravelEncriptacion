<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'J3!brisa-Segura.26';

    #[TestWith([500])]
    #[TestWith([499])]
    #[TestWith([300])]
    public function test_each_level_can_sign_in_see_its_own_profile_and_change_its_password(int $level): void
    {
        $user = User::factory()->withPassword(self::PASSWORD)->create(['role_level' => $level]);
        $other = User::factory()->create();
        $newPassword = 'T8!cumbre-Clara.31';

        $this->post(route('login.store'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('mask'));
        $this->get(route('profile.edit', ['user' => $other->id]))->assertOk()->assertSee($user->email)->assertDontSee($other->email);
        $this->put(route('profile.password'), [
            'current_password' => self::PASSWORD,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
            'user_id' => $other->id,
            'role_level' => 500,
        ])->assertSessionHasNoErrors();

        $this->assertSame($level, $user->fresh()->role_level);
        $this->assertNotSame($user->password, $user->fresh()->password);
        $this->assertSame($other->password, $other->fresh()->password);
    }

    #[TestWith([499, true])]
    #[TestWith([300, false])]
    public function test_read_only_navigation_and_admin_route_permissions(int $level, bool $canRead): void
    {
        $actor = User::factory()->create(['role_level' => $level]);
        $target = User::factory()->create(['temp_requested_at' => now()]);
        $id = $target->id;
        $this->actingAs($actor);

        $dashboard = $this->get(route('dashboard'))->assertOk()->assertDontSee(route('admin.hashes.index'));
        foreach (['admin.users.index', 'admin.audit.index'] as $route) {
            $canRead ? $dashboard->assertSee(route($route)) : $dashboard->assertDontSee(route($route));
            $this->get(route($route))->assertStatus($canRead ? 200 : 403);
        }

        if ($canRead) {
            $this->get(route('admin.users.index'))->assertSee($target->email)
                ->assertDontSee('Nuevo usuario')->assertDontSee('Editar datos')
                ->assertDontSee('Solicitó clave temporal')->assertDontSee('Guardar')
                ->assertDontSee(route('admin.users.deactivate', $target));
        }

        $this->get(route('admin.hashes.index'), ['Sec-Fetch-Dest' => 'document'])->assertForbidden();
        $this->post(route('admin.users.store'))->assertForbidden();
        $this->patch(route('admin.users.update', $target), ['name' => 'Cambio no autorizado'])->assertForbidden();
        $this->patch(route('admin.users.level', $target), ['role_level' => 499])->assertForbidden();
        $this->patch(route('admin.users.deactivate', $target))->assertForbidden();
        $this->post(route('admin.users.temp', $target))->assertForbidden();
        $this->delete('/admin/usuarios/'.$id)->assertMethodNotAllowed();

        $this->assertDatabaseHas('users', ['id' => $id, 'name' => $target->name, 'role_level' => 300, 'deactivated_at' => null]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_level_500_can_edit_other_users_and_assign_only_levels_499_and_300(): void
    {
        $admin = User::factory()->create(['role_level' => 500]);
        $target = User::factory()->create();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()
            ->assertSee(route('admin.users.update', $target))->assertSee(route('admin.hashes.index'));

        $this->patch(route('admin.users.update', $target), [
            'name' => 'Nombre nuevo',
            'email' => 'NUEVA@example.test',
            'role_level' => 500,
            'deactivated_at' => now(),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'id' => $target->id, 'name' => 'Nombre nuevo', 'email' => 'nueva@example.test',
            'email_verified_at' => null, 'role_level' => 300, 'deactivated_at' => null, 'password' => $target->password,
        ]);

        $legacy = User::factory()->create(['crypto_version' => 1]);
        $this->patch(route('admin.users.update', $legacy), ['name' => 'Nombre heredado', 'email' => 'otro@example.test'])
            ->assertSessionHasErrors('email');
        $this->patchJson(route('admin.users.update', $legacy), ['name' => 'Nombre heredado'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $legacy->id, 'name' => 'Nombre heredado', 'email' => $legacy->email]);

        $this->patch(route('admin.users.level', $target), ['role_level' => 499])->assertSessionHasNoErrors();
        $this->assertSame(499, $target->fresh()->role_level);
        $this->patch(route('admin.users.level', $target), ['role_level' => 300])->assertSessionHasNoErrors();
        $this->assertSame(300, $target->fresh()->role_level);
        $this->patch(route('admin.users.level', $target), ['role_level' => 500])->assertSessionHasErrors('role_level');

        $this->post(route('admin.users.store'), [
            'name' => 'Cuenta nueva', 'email' => 'cuenta@example.test',
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'role_level' => 500,
        ])->assertSessionHasErrors('role_level');
        $this->assertDatabaseMissing('users', ['email' => 'cuenta@example.test']);
    }

    public function test_deactivation_preserves_the_account_and_blocks_existing_and_new_sessions(): void
    {
        config(['session.driver' => 'database']);
        $admin = User::factory()->create(['role_level' => 500]);
        $user = User::factory()->withPassword(self::PASSWORD)->create(['temp_requested_at' => now()]);
        DB::table('sessions')->insert([
            'id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($admin)->delete('/admin/usuarios/'.$user->id)->assertMethodNotAllowed();
        $this->patch(route('admin.users.deactivate', $admin))->assertSessionHas('error');
        $this->patch(route('admin.users.deactivate', $user))->assertSessionHas('status');
        $deactivated = $user->fresh();
        $this->assertNotNull($deactivated->deactivated_at);
        $this->assertNull($deactivated->temp_requested_at);
        $this->assertNotSame($user->remember_token, $deactivated->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);

        $this->actingAs($deactivated)->get(route('mask'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');
        $this->post(route('password.recover.store'), ['email' => $user->email])->assertSessionHas('status');
        $this->assertNull($user->fresh()->temp_requested_at);
        $this->assertGuest();
    }

    public function test_each_hash_view_is_logged_without_disclosing_secrets_in_audit_or_exporting_hashes(): void
    {
        $admin = User::factory()->create(['role_level' => 500]);
        $temporaryHash = Hash::make('Q4!sendero-Verde.29');
        $target = User::factory()->withPassword(self::PASSWORD)->create(['temp_password_hash' => $temporaryHash]);
        $malformed = User::factory()->create(['password' => 'valor almacenado sin hash']);
        $viewer = User::factory()->create(['role_level' => 499]);

        $this->actingAs($admin);
        for ($consultation = 0; $consultation < 2; $consultation++) {
            $response = $this->get(route('admin.hashes.index'))->assertOk()
                ->assertSee($target->password)
                ->assertDontSee($target->salt)
                ->assertDontSee($temporaryHash)
                ->assertDontSee($malformed->password)
                ->assertDontSee(self::PASSWORD)
                ->assertDontSee('Exportar');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }

        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'actor_level' => 500, 'action' => 'password_hashes.viewed']);
        $this->get(route('admin.audit.index'))->assertOk()->assertSee('Consulta de hashes');
        $this->flushSession();
        $this->actingAs($viewer)->get(route('admin.audit.index'))->assertOk()
            ->assertSee('Consulta de hashes')->assertDontSee($target->password)->assertDontSee($target->salt);
        $this->post(route('admin.hashes.index'))->assertMethodNotAllowed();
        $this->get('/admin/hashes/exportar')->assertNotFound();
    }

    public function test_hashes_are_withheld_when_the_audit_record_cannot_be_written(): void
    {
        $admin = User::factory()->create(['role_level' => 500]);
        Schema::drop('audit_logs');

        $this->actingAs($admin)->get(route('admin.hashes.index'))->assertStatus(503)->assertDontSee($admin->password);
    }
}
