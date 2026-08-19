<?php

namespace Tests\Feature;

use Alpha\Journal\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_suspended_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $user->suspend();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_an_active_user_can_still_log_in(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_reactivating_a_suspended_user_allows_login_again(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $user->suspend();
        $user->reactivate();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_suspend_and_reactivate_track_the_suspended_at_timestamp(): void
    {
        $user = User::factory()->create();

        $user->suspend();
        $this->assertTrue($user->fresh()->is_suspended);
        $this->assertNotNull($user->fresh()->suspended_at);

        $user->reactivate();
        $this->assertFalse($user->fresh()->is_suspended);
        $this->assertNull($user->fresh()->suspended_at);
    }
}
