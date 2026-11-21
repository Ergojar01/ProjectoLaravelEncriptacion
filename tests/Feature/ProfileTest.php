<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed_inside_the_portal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'), ['Sec-Fetch-Dest' => 'iframe'])
            ->assertOk()->assertSee($user->name)->assertSee($user->email);
    }

    public function test_profile_route_does_not_accept_changes_to_account_information(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.edit'), [
            'name' => 'Nombre nuevo',
            'email' => 'nuevo@example.test',
        ])->assertMethodNotAllowed();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
    }

    public function test_admin_edit_preserves_verification_when_the_email_address_is_unchanged(): void
    {
        $admin = User::factory()->create(['role_level' => 500]);
        $user = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $user), [
            'name' => 'Nombre nuevo',
            'email' => $user->email,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Nombre nuevo', $user->fresh()->name);
        $this->assertTrue($user->fresh()->email_verified_at->equalTo($user->email_verified_at));
    }

    #[TestWith([499])]
    #[TestWith([300])]
    public function test_lower_levels_cannot_delete_their_own_profile(int $level): void
    {
        $user = User::factory()->create(['role_level' => $level]);

        $this->actingAs($user)->delete(route('profile.edit'))->assertMethodNotAllowed();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deactivated_at' => null]);
    }

    public function test_legacy_profile_delete_path_is_unavailable(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/profile')->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deactivated_at' => null]);
    }
}
