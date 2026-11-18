<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT = 'Current#Pass123';

    private const NEW = 'NewPassword#123';

    public function test_password_can_be_updated_with_current_password_and_confirmation(): void
    {
        $user = User::factory()->withPassword(self::CURRENT)->create();
        $oldToken = $user->remember_token;

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
        $this->put(route('profile.password'), [
            'current_password' => self::CURRENT,
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check(self::NEW, $user->password));
        $this->assertFalse(Hash::check(self::CURRENT, $user->password));
        $this->assertNotSame($oldToken, $user->remember_token);
        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_password_change_rejects_absent_incorrect_and_reused_credentials(): void
    {
        $user = User::factory()->withPassword(self::CURRENT)->create();
        $original = $user->password;
        $this->actingAs($user);

        $this->put(route('profile.password'), [
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertSessionHasErrors(['current_password' => 'Escribe tu contraseña actual.']);

        $this->put(route('profile.password'), [
            'current_password' => 'incorrecta',
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertSessionHasErrors(['current_password' => 'La contraseña actual no es correcta.'])
            ->assertSessionMissing('_old_input.current_password');

        $this->put(route('profile.password'), [
            'current_password' => self::CURRENT,
            'password' => self::CURRENT,
            'password_confirmation' => self::CURRENT,
        ])->assertSessionHasErrors('password');

        $this->assertSame($original, $user->refresh()->password);
    }
}
