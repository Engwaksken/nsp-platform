<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_reset_link_is_sent_with_a_generic_answer(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'staff@hive.test']);

        $known = $this->post('/forgot-password', ['email' => 'staff@hive.test'])->assertSessionHas('status');
        $unknown = $this->post('/forgot-password', ['email' => 'nobody@hive.test'])->assertSessionHas('status');

        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_no_link_for_sso_only_or_inactive_accounts(): void
    {
        Notification::fake();
        $sso = User::factory()->create(['email' => 'sso@hive.test']);
        $sso->forceFill(['password' => null])->save();
        User::factory()->inactive()->create(['email' => 'gone@hive.test']);

        $this->post('/forgot-password', ['email' => 'sso@hive.test']);
        $this->post('/forgot-password', ['email' => 'gone@hive.test']);

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_and_other_sessions_end(): void
    {
        $user = User::factory()->create(['email' => 'staff@hive.test']);
        \DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token, 'email' => 'staff@hive.test',
            'password' => 'NewPassword2026', 'password_confirmation' => 'NewPassword2026',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('NewPassword2026', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_reset']);
    }

    public function test_bad_token_and_weak_password_are_refused(): void
    {
        $user = User::factory()->create(['email' => 'staff@hive.test']);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', ['token' => 'forged', 'email' => 'staff@hive.test', 'password' => 'NewPassword2026', 'password_confirmation' => 'NewPassword2026'])
            ->assertSessionHasErrors('email');
        $this->post('/reset-password', ['token' => $token, 'email' => 'staff@hive.test', 'password' => 'short1', 'password_confirmation' => 'short1'])
            ->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_requests_are_rate_limited(): void
    {
        Notification::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => 'x@hive.test'])->assertRedirect();
        }
        $this->post('/forgot-password', ['email' => 'x@hive.test'])->assertStatus(429);
    }
}
