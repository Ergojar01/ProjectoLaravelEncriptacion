<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creation_requires_the_same_policy_and_confirmation_as_registration(): void
    {
        $admin = User::factory()->create(['role_level' => 500]);
        $this->actingAs($admin);
        $route = route('admin.users.store');
        $fields = [
            'name' => 'Persona nueva',
            'email' => 'nueva@example.test',
            'role_level' => 499,
        ];

        $this->post($route, $fields + ['password' => 'weak', 'password_confirmation' => 'weak'])
            ->assertSessionHasErrors('password');
        $this->post($route, $fields + ['password' => 'Aa1?bbbbbbbb'])
            ->assertSessionHasErrors(['password' => 'La confirmación debe coincidir con la contraseña.']);

        $this->assertDatabaseCount('users', 1);
        $this->post($route, $fields + ['password' => 'Aa1?bbbbbbbb', 'password_confirmation' => 'Aa1?bbbbbbbb'])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $user = User::where('email', $fields['email'])->sole();
        $this->assertSame(499, $user->role_level);
        $this->assertTrue(Hash::check('Aa1?bbbbbbbb', $user->password));
    }
}
