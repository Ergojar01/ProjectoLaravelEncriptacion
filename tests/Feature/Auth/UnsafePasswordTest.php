<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UnsafePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_common_password_variant_is_rejected_locally(): void
    {
        Http::preventStrayRequests();

        $this->register('Password123!')->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_exact_password_in_local_table_is_rejected(): void
    {
        Http::preventStrayRequests();
        $password = 'Example#Breach8197';
        config(['password_blocklist' => [hash('sha256', $password) => true]]);

        $this->register($password)->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_outside_local_table_can_be_saved_without_network_access(): void
    {
        Http::preventStrayRequests();

        $this->register('Offline#Example8197')->assertRedirect(route('mask'));

        $this->assertDatabaseCount('users', 1);
    }

    private function register(string $password): TestResponse
    {
        return $this->postJson(route('register.store'), [
            'name' => 'Usuario nuevo',
            'email' => 'nuevo@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }
}
