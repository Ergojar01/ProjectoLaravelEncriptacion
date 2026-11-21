<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = 'AdminCurrent#123';

    private const CURRENT = 'Current#Pass123';

    private const TEMPORARY = 'Temporary#123';

    private const NEW = 'NewPassword#123';

    public function test_recovery_request_is_generic_and_marks_supported_accounts(): void
    {
        $user = User::factory()->create();
        $this->get(route('password.recover'))->assertOk();

        $this->post(route('password.recover.store'), ['email' => $user->email])
            ->assertSessionHasNoErrors();
        $status = session('status');

        $this->post(route('password.recover.store'), ['email' => 'missing@example.test'])
            ->assertSessionHas('status', $status);

        $this->assertNotNull($user->refresh()->temp_requested_at);
        $this->assertDatabaseHas('password_resets', [
            'user_id' => $user->id, 'requested_at' => $user->temp_requested_at, 'token_hash' => null,
        ]);
    }

    public function test_recovery_requires_admin_reauthentication_and_confirmed_temporary_password(): void
    {
        $admin = User::factory()->withPassword(self::ADMIN)->create(['role_level' => 500]);
        $user = User::factory()->withPassword(self::CURRENT)->create();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()
            ->assertDontSee(substr($user->password, 0, 24))
            ->assertSee('Nueva clave temporal');

        $route = route('admin.users.temp', $user);
        $this->post($route, $this->issuance('incorrecta'))->assertSessionHasErrors('current_password');
        $this->post($route, array_replace($this->issuance(), ['password_confirmation' => 'incorrecta']))
            ->assertSessionHasErrors('password');
        $this->post($route, array_replace($this->issuance(), [
            'password' => self::CURRENT, 'password_confirmation' => self::CURRENT,
        ]))->assertSessionHasErrors('password');

        $this->assertNull($user->refresh()->temp_password_hash);
    }

    public function test_non_administrators_cannot_issue_temporary_credentials(): void
    {
        $viewer = User::factory()->withPassword(self::ADMIN)->create(['role_level' => 499]);
        $user = User::factory()->create();

        $this->actingAs($viewer)->post(route('admin.users.temp', $user), $this->issuance())
            ->assertForbidden();

        $this->assertNull($user->refresh()->temp_password_hash);
    }

    public function test_temporary_credential_is_single_use_and_final_change_checks_current_and_previous_passwords(): void
    {
        $admin = User::factory()->withPassword(self::ADMIN)->create(['role_level' => 500]);
        $user = User::factory()->withPassword(self::CURRENT)->create();
        $original = $user->password;

        $this->actingAs($admin)->post(route('admin.users.temp', $user), $this->issuance())
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('temp_password');

        $user->refresh();
        $this->assertSame($original, $user->password);
        $this->assertTrue(Hash::check(self::TEMPORARY, $user->temp_password_hash));
        $this->assertArrayNotHasKey('temp_password_hash', $user->toArray());
        $this->assertDatabaseHas('password_resets', [
            'user_id' => $user->id, 'token_hash' => $user->temp_password_hash,
            'expires_at' => $user->temp_password_expires_at, 'used_at' => null,
        ]);

        Auth::forgetGuards();
        $this->flushSession();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => self::CURRENT])
            ->assertSessionHasErrors('email');
        $this->post(route('login.store'), ['email' => $user->email, 'password' => self::TEMPORARY, 'remember' => true])
            ->assertRedirect(route('mask'))
            ->assertCookieMissing(Auth::guard()->getRecallerName());
        $this->assertDatabaseHas('password_resets', [
            'user_id' => $user->id, 'used_at' => $user->fresh()->temp_password_used_at,
        ]);

        $session = session()->all();
        Auth::forgetGuards();
        $this->flushSession();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => self::TEMPORARY])
            ->assertSessionHasErrors('email');

        $this->flushSession();
        $this->withSession($session)->get(route('dashboard'))->assertRedirect(route('profile.edit'));
        $this->get(route('profile.edit'))->assertOk()->assertSee('Clave temporal actual');
        $this->put(route('profile.password'), [
            'password' => self::NEW, 'password_confirmation' => self::NEW,
        ])->assertSessionHasErrors('current_password');

        $this->putJson(route('profile.password'), array_replace($this->change(self::NEW), [
            'current_password' => 'incorrecta',
        ]))->assertUnprocessable()
            ->assertJsonPath('errors.current_password.0', 'La contraseña actual no es correcta.');

        foreach ([self::TEMPORARY, self::CURRENT] as $used) {
            $this->put(route('profile.password'), $this->change($used))->assertSessionHasErrors('password');
        }

        $this->put(route('profile.password'), $this->change(self::NEW))
            ->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->temp_password_hash);
        $this->assertTrue(Hash::check(self::NEW, $user->password));
        $this->assertDatabaseMissing('password_resets', ['user_id' => $user->id]);
    }

    public function test_expired_temporary_credential_cannot_be_used(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'must_change_password' => true,
            'temp_password_hash' => Hash::make(self::TEMPORARY),
            'temp_password_expires_at' => now()->subMinute(),
        ])->save();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => self::TEMPORARY])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull($user->refresh()->temp_password_used_at);
    }

    public function test_temporary_session_expires_before_password_write(): void
    {
        $user = User::factory()->create();
        $original = $user->password;
        $user->forceFill([
            'must_change_password' => true,
            'temp_password_hash' => Hash::make(self::TEMPORARY),
            'temp_password_expires_at' => now()->addHours(24),
        ])->save();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => self::TEMPORARY])
            ->assertRedirect(route('mask'));

        $this->travel(6)->minutes();
        $this->put(route('profile.password'), $this->change(self::NEW));
        $this->assertGuest();
        $this->assertSame($original, $user->refresh()->password);
    }

    private function issuance(string $current = self::ADMIN): array
    {
        return [
            'current_password' => $current,
            'password' => self::TEMPORARY,
            'password_confirmation' => self::TEMPORARY,
        ];
    }

    private function change(string $password): array
    {
        return [
            'current_password' => self::TEMPORARY,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }
}
