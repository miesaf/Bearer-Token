<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_the_auth_pages(): void
    {
        $this->get('/login')->assertOk()->assertSee('Login');
        $this->get('/register')->assertOk()->assertSee('Register');
        $this->get('/password/reset')->assertOk()->assertSee('Reset Password');
    }

    public function test_guest_is_sent_to_login_from_home(): void
    {
        $this->get('/home')->assertRedirect('/login');
    }

    public function test_signed_in_user_sees_home_and_is_kept_off_guest_pages(): void
    {
        $user = User::factory()->create(['name' => 'Demo User']);

        $this->actingAs($user)->get('/home')
            ->assertOk()
            ->assertSee('You are logged in!')
            ->assertSee('Demo User');

        $this->actingAs($user)->get('/login')->assertRedirect('/home');
    }

    public function test_a_visitor_can_register(): void
    {
        $this->post('/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect('/home');

        $this->assertAuthenticated();
        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_a_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_a_password_reset_link_is_sent(): void
    {
        $user = User::factory()->create();

        $this->post('/password/email', ['email' => $user->email])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('password_resets', ['email' => $user->email]);
    }
}
