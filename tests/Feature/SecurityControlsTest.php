<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireCsrfToken;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityControlsTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'J3!brisa-Segura.26';

    public function test_account_lock_survives_a_different_session_and_origin(): void
    {
        $this->freezeTime();
        $user = User::factory()->withPassword(self::PASSWORD)->create();
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Contraseña incorrecta'])
                ->assertSessionHasErrors('email');
        }
        $this->assertDatabaseCount('login_attempts', 3);
        $this->assertDatabaseHas('login_attempts', ['user_id' => $user->id, 'ip_address' => '127.0.0.1']);
        $this->assertDatabaseHas('account_locks', ['user_id' => $user->id, 'failures' => 3]);

        $this->flushSession();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50'])
            ->post(route('login.store'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHas('lock_seconds', 180);
        $this->assertGuest();
        $this->assertDatabaseCount('login_attempts', 3);
    }

    public function test_account_lookup_preserves_punctuation_as_literal_data(): void
    {
        $user = User::factory()->withPassword(self::PASSWORD)->create(['email' => "o'connor@example.test"]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('mask'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_profile_renders_markup_in_names_as_text(): void
    {
        $user = User::factory()->create(['name' => '<em>Laboratorio</em> & equipo']);
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertSee($user->name)
            ->assertDontSee($user->name, escape: false);
    }

    public function test_modification_requires_a_csrf_token_even_from_the_same_origin(): void
    {
        $this->app->bind(RequireCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends RequireCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });

        $admin = User::factory()->create(['role_level' => 500]);
        $target = User::factory()->create();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertSee('name="_token"', escape: false);
        $this->patch(route('admin.users.level', $target), ['role_level' => 499], ['Sec-Fetch-Site' => 'same-origin'])
            ->assertStatus(419);
        $this->assertSame(300, $target->fresh()->role_level);

        $this->patch(route('admin.users.level', $target), ['role_level' => 499, '_token' => session()->token()])
            ->assertSessionHasNoErrors();
        $this->assertSame(499, $target->fresh()->role_level);
    }

    public function test_logout_revokes_the_database_session_and_rotates_its_token(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->withPassword(self::PASSWORD)->create();
        $session = $this->signIn($user);
        $token = session()->token();
        $this->assertDatabaseHas('sessions', ['id' => $session, 'user_id' => $user->id]);

        $this->withCookie(config('session.cookie'), $session)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertNotSame($token, session()->token());
        $this->assertDatabaseMissing('sessions', ['id' => $session]);

        $this->newBrowser();
        $this->withCookie(config('session.cookie'), $session)->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_change_revokes_other_database_sessions_and_keeps_the_new_session(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->withPassword(self::PASSWORD)->create();
        $other = $this->signIn($user);
        $this->newBrowser();
        $active = $this->signIn($user);
        $this->assertNotSame($other, $active);
        $this->assertDatabaseHas('sessions', ['id' => $other, 'user_id' => $user->id]);
        $newPassword = 'T8!cumbre-Clara.31';

        $response = $this->withCookie(config('session.cookie'), $active)->put(route('profile.password'), [
            'current_password' => self::PASSWORD,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertSessionHasNoErrors();
        $renewed = $response->getCookie(config('session.cookie'))->getValue();
        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => $other]);
        $this->assertDatabaseMissing('sessions', ['id' => $active]);
        $this->assertDatabaseHas('sessions', ['id' => $renewed, 'user_id' => $user->id]);

        $this->newBrowser();
        $this->withCookie(config('session.cookie'), $other)->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->newBrowser();
        $this->withCookie(config('session.cookie'), $renewed)->get(route('profile.edit'))->assertOk();
    }

    public function test_new_accounts_have_unique_usernames_and_referenced_roles(): void
    {
        $users = User::factory()->count(2)->create();
        $this->assertNotEmpty($users[0]->username);
        $this->assertNotSame($users[0]->username, $users[1]->username);
        $this->assertDatabaseHas('roles', ['id' => 300]);
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $users[0]->id)->update(['role_level' => 501]);
    }

    private function newBrowser(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }

    private function signIn(User $user): string
    {
        return $this->post(route('login.store'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('mask'))->getCookie(config('session.cookie'))->getValue();
    }
}
