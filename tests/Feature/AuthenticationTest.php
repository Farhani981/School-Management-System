<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('panel-login:127.0.0.1');
    }

    public function test_login_and_forgot_password_are_separate_guest_pages(): void
    {
        $this->get('/login')->assertOk()->assertSee('USER LOGIN')->assertSee('Remember me')->assertSee('Forgot password?')->assertDontSee('id="sidebar"', false)->assertDontSee('Attendance Sign In');
        $this->get('/forgot-password')->assertOk()->assertSee('SEND RESET LINK')->assertDontSee('id="sidebar"', false);
        $this->get('/attendance/login')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/teacher/dashboard')->assertRedirect('/login');
        $this->get('/logout')->assertStatus(405);
    }

    public function test_logout_invalidates_session_and_admin_relogin_ignores_old_attendance_destination(): void
    {
        $user = User::factory()->create(['role' => 'Admin', 'email' => 'admin@test.com', 'password' => 'password']);
        $this->actingAs($user)->withSession(['url.intended' => '/attendance/students', 'private_value' => 'old', '_token' => 'old-token']);
        $oldId = session()->getId();
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('url.intended')->assertSessionMissing('private_value')->assertSessionHas('status', 'Logged out successfully.');
        $this->assertGuest();
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame('old-token', session()->token());
        $this->get('/dashboard')->assertRedirect('/login');
        $this->withSession(['url.intended' => '/attendance/students'])->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard')->assertSessionMissing('url.intended');
        $this->assertAuthenticatedAs($user);
    }

    public function test_teacher_and_principal_login_and_authenticated_guest_redirects(): void
    {
        foreach (['Teacher' => '/teacher/dashboard', 'Principal' => '/dashboard'] as $role => $destination) {
            $user = User::factory()->create(['role' => $role, 'password' => 'password']);
            $this->withSession(['url.intended' => '/fees/setup/types'])->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($destination)->assertSessionMissing('url.intended');
            $this->get('/login')->assertRedirect($destination);
            $this->get('/forgot-password')->assertRedirect($destination);
            $this->post('/logout')->assertRedirect('/login');
        }
    }

    public function test_authenticated_pages_and_logout_are_not_cacheable(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        foreach (['/dashboard', '/students'] as $path) {
            $response = $this->actingAs($admin)->get($path)->assertOk()->assertHeader('Pragma', 'no-cache');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        $response = $this->post('/logout')->assertRedirect('/login');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $teacher = User::factory()->create(['role' => 'Teacher']);
        $response = $this->actingAs($teacher)->get('/teacher/dashboard')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_validation_remember_me_and_login_throttling(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);
        for ($i=0; $i<5; $i++) {
            $this->post('/login', ['email' => 'wrong'.$i.'@test.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $user = User::factory()->create(['role' => 'Admin', 'password' => 'password']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => 1])->assertRedirect('/dashboard')->assertCookie(Auth::guard()->getRecallerName());
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_forgot_password_sends_reset_link_and_can_reset_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => 'Teacher', 'password' => 'old-password']);
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) { $token = $notification->token; return true; });
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))->assertOk()->assertSee('RESET PASSWORD');
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])->assertRedirect('/login');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->post('/login', ['email' => $user->email, 'password' => 'new-password-123'])->assertRedirect('/teacher/dashboard');
    }

    public function test_expired_session_shows_a_login_flash_message(): void
    {
        \Illuminate\Support\Facades\Route::post('/test-expired-session', function () {
            throw new \Illuminate\Session\TokenMismatchException('CSRF token mismatch.');
        })->middleware('web');
        $this->post('/test-expired-session')->assertRedirect('/login')->assertSessionHas('status', 'Your session expired. Please sign in again.');
        $this->get('/login')->assertSee('Your session expired. Please sign in again.');
    }
}
