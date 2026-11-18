<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Current#Pass123';

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Iniciar sesión');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->withPassword(self::PASSWORD)->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasNoErrors()->assertRedirect(route('mask'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->withPassword(self::PASSWORD)->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'contraseña-incorrecta'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_successful_legacy_login_upgrades_the_hash(): void
    {
        $pepper = str_repeat('a', 64);
        config(['crypto.pepper' => $pepper, 'crypto.iterations' => 1000]);
        $salt = str_repeat('01', 32);
        $saltBytes = hex2bin($salt);
        $email = 'legacy@example.test';
        $userHash = hash_hmac('sha512', $email, $pepper.$saltBytes, true);
        $passwordHash = hash_pbkdf2('sha512', self::PASSWORD, $saltBytes.$pepper, 1000, 64, true);

        $user = User::factory()->create([
            'email' => $email,
            'salt' => $salt,
            'crypto_version' => 1,
            'password' => hash_hmac('sha512', $saltBytes.($userHash ^ $passwordHash), $pepper),
        ]);

        $this->post(route('login.store'), ['email' => $email, 'password' => self::PASSWORD])
            ->assertRedirect(route('mask'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(2, $user->refresh()->crypto_version);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
    }

    public function test_users_can_logout(): void
    {
        $this->actingAs(User::factory()->create())->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }
}
