<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function credentials(): array
    {
        return ['name' => 'Leitora', 'email' => 'leitora@example.com', 'password' => 'senha-segura', 'password_confirmation' => 'senha-segura'];
    }

    public function test_registration_normalizes_email_hashes_password_and_authenticates(): void
    {
        $data = $this->credentials();
        $data['email'] = ' LEITORA@EXAMPLE.COM ';
        $this->post('/register', $data)->assertRedirect('/dashboard');
        $user = User::sole();
        $this->assertSame('leitora@example.com', $user->email);
        $this->assertTrue(Hash::check('senha-segura', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
    }

    public function test_registration_rejects_invalid_fields_and_confirmation(): void
    {
        $this->post('/register', ['name' => '', 'email' => 'invalido', 'password' => '123', 'password_confirmation' => '456'])
            ->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'leitora@example.com']);
        $this->post('/register', $this->credentials())->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_login_authenticates_and_honors_intended_destination(): void
    {
        $user = User::factory()->create(['password' => 'senha-segura']);
        $this->withSession(['url.intended' => '/dashboard?origem=login'])
            ->post('/login', ['email' => strtoupper($user->email), 'password' => 'senha-segura'])
            ->assertRedirect('/dashboard?origem=login');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_login_keeps_user_guest_and_never_flashes_password(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'incorreta'])
            ->assertSessionHasErrors('email')->assertSessionMissing('_old_input.password');
        $this->assertGuest();
    }

    public function test_login_attempts_are_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'limite@example.com', 'password' => 'incorreta']);
        }
        $this->post('/login', ['email' => 'limite@example.com', 'password' => 'incorreta'])->assertStatus(429);
    }

    public function test_registration_and_recovery_requests_are_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', []);
        }
        $this->post('/register', [])->assertStatus(429);
        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', ['email' => 'unknown@example.com']);
        }
        $this->post('/forgot-password', ['email' => 'unknown@example.com'])->assertStatus(429);
    }

    public function test_dashboard_is_protected_and_shares_only_public_user_data(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')->where('auth.user.id', $user->id)
            ->where('auth.user.name', $user->name)->where('auth.user.email', $user->email)
            ->missing('auth.user.password')->missing('auth.user.remember_token')->has('auth.user', 3));
        $this->get('/login')->assertRedirect('/dashboard');
        $this->get('/register')->assertRedirect('/dashboard');
    }

    public function test_logout_ends_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['private' => 'value'])->post('/logout')->assertRedirect('/')->assertSessionMissing('private');
        $this->assertGuest();
    }

    public function test_recovery_sends_notification_and_hides_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        $status = session('status');
        Notification::assertSentTo($user, ResetPassword::class);
        $this->post('/forgot-password', ['email' => 'unknown@example.com'])->assertSessionHas('status', $status);
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status', $status);
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_password_can_be_reset_once_and_old_password_stops_working(): void
    {
        $user = User::factory()->create(['password' => 'senha-antiga', 'remember_token' => 'anterior']);
        $token = Password::createToken($user);
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'senha-nova', 'password_confirmation' => 'senha-nova'];
        $this->post('/reset-password', $data)->assertRedirect('/login')->assertSessionHas('status');
        $this->assertGuest();
        $this->assertTrue(Hash::check('senha-nova', $user->fresh()->password));
        $this->assertNotSame('anterior', $user->fresh()->remember_token);
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'senha-antiga'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'senha-nova'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_and_expired_reset_tokens_are_rejected(): void
    {
        $user = User::factory()->create();
        $data = ['email' => $user->email, 'token' => 'invalid', 'password' => 'senha-nova', 'password_confirmation' => 'senha-nova'];
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $data['token'] = Password::createToken($user);
        $this->travel(61)->minutes();
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
    }

    public function test_recovery_transport_failure_produces_safe_error(): void
    {
        Password::shouldReceive('sendResetLink')->once()->andThrow(new \RuntimeException('secret transport details'));
        $this->post('/forgot-password', ['email' => 'leitora@example.com'])->assertSessionHasErrors('email');
        $this->assertStringNotContainsString('secret', session('errors')->first('email'));
    }

    public function test_password_confirmation_and_multibyte_length_are_validated(): void
    {
        $data = $this->credentials();
        $data['password'] = str_repeat('á', 40);
        $data['password_confirmation'] = $data['password'];
        $this->post('/register', $data)->assertSessionHasErrors('password');
        $user = User::factory()->create();
        $this->post('/reset-password', [
            'email' => $user->email, 'token' => Password::createToken($user),
            'password' => 'senha-nova', 'password_confirmation' => 'outra-senha',
        ])->assertSessionHasErrors('password');
    }

    public function test_throttled_inertia_forms_receive_portuguese_feedback(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'inertia-limit@example.com', 'password' => 'incorreta']);
        }
        $this->from('/login')->withHeader('X-Inertia', 'true')
            ->post('/login', ['email' => 'inertia-limit@example.com', 'password' => 'incorreta'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertStringContainsString('Muitas tentativas', session('errors')->first('email'));
    }

    public function test_reset_notification_is_portuguese_and_uses_configured_app_url(): void
    {
        $user = User::factory()->create();
        config(['app.url' => 'http://localhost:8001']);
        $mail = (new ResetPassword('test-token'))->toMail($user);
        $this->assertStringStartsWith('http://localhost:8001/reset-password/test-token?', $mail->actionUrl);
        $this->assertSame('Redefinir minha senha', $mail->actionText);
        $this->assertStringContainsString('Redefina sua senha', $mail->subject);
    }
}
