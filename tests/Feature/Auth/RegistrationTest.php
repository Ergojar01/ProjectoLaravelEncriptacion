<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_form_renders(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Entre 12 y 64 caracteres')
            ->assertSee('name="password_confirmation"', false);
    }

    #[DataProvider('invalidPasswords')]
    public function test_registration_reports_each_password_requirement(mixed $password, string $message): void
    {
        $this->postJson(route('register.store'), [
            'name' => 'Usuario nuevo',
            'email' => 'nuevo@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password')
            ->assertJsonFragment(['password' => [$message]]);

        $this->assertDatabaseCount('users', 0);
    }

    public static function invalidPasswords(): array
    {
        return [
            '11 caracteres' => ['Aa1!bbbbbbb', 'La contraseña debe tener al menos 12 caracteres.'],
            '65 caracteres' => ['Aa1!'.str_repeat('é', 61), 'La contraseña no puede superar los 64 caracteres.'],
            'mayúscula ASCII' => ['áa1!bbbbbbbb', 'La contraseña debe incluir al menos una mayúscula (A-Z).'],
            'minúscula ASCII' => ['AÁ1!BBBBBBBB', 'La contraseña debe incluir al menos una minúscula (a-z).'],
            'número ASCII' => ['Aa١!bbbbbbbb', 'La contraseña debe incluir al menos un número (0-9).'],
            'carácter especial' => ['Aa1-bbbbbbbb', 'La contraseña debe incluir al menos un carácter especial de este conjunto: @$!%*?&#.'],
            'texto' => [['Aa1!bbbbbbbb'], 'La contraseña debe ser texto.'],
        ];
    }

    public function test_multiple_complexity_errors_are_reported_in_spanish(): void
    {
        $this->postJson(route('register.store'), [
            'name' => 'Usuario nuevo',
            'email' => 'nuevo@example.test',
            'password' => 'abcdefghijkl',
            'password_confirmation' => 'abcdefghijkl',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'La contraseña debe incluir al menos una mayúscula (A-Z).')
            ->assertJsonCount(3, 'errors.password');
    }

    public function test_registration_requires_confirmation_and_keeps_secrets_out_of_old_input(): void
    {
        $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Usuario nuevo',
            'email' => 'nuevo@example.test',
            'password' => 'Aa1!bbbbbbbb',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['password' => 'La confirmación debe coincidir con la contraseña.'])
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->assertDatabaseCount('users', 0);
    }

    #[DataProvider('validBoundaries')]
    public function test_registration_hashes_valid_passwords_without_truncating_them(string $password): void
    {
        $this->post(route('register.store'), [
            'name' => 'Usuario nuevo',
            'email' => 'nuevo@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasNoErrors()->assertRedirect(route('mask'));

        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('argon2id', Hash::info($user->password)['algoName']);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertFalse(Hash::check(substr($password, 0, -1).'x', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public static function validBoundaries(): array
    {
        return [
            '12 caracteres' => ['Aa1!bbbbbbbb'],
            '64 caracteres multibyte' => ['Aa1#'.str_repeat('é', 60)],
        ];
    }
}
