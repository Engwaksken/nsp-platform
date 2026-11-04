<?php

namespace Tests\Feature;

use App\Models\LoginAudit;
use App\Models\User;
use App\Support\Features;
use Tests\TestCase;

class LocalLoginTest extends TestCase
{
    public function test_admin_signs_in_and_lands_on_the_dashboard(): void
    {
        $admin = User::factory()->withRole('platform_admin')->create(['email' => 'admin@hive.test']);

        $this->post('/login', ['email' => 'ADMIN@hive.test', 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->last_login_at);
        $this->assertTrue(LoginAudit::query()->where('user_id', $admin->id)->where('success', true)->exists());
    }

    public function test_startup_user_lands_on_the_workspace(): void
    {
        User::factory()->withRole('startup_user')->create(['email' => 'founder@hive.test']);

        $this->post('/login', ['email' => 'founder@hive.test', 'password' => 'password'])->assertRedirect('/workspace');
        $this->get('/workspace')->assertOk()->assertSee('Not available yet');
    }

    public function test_wrong_password_and_unknown_email_get_the_same_message(): void
    {
        User::factory()->create(['email' => 'a@hive.test']);

        $this->post('/login', ['email' => 'a@hive.test', 'password' => 'nope'])->assertSessionHasErrors(['email' => 'The email or password is incorrect.']);
        $this->post('/login', ['email' => 'b@hive.test', 'password' => 'nope'])->assertSessionHasErrors(['email' => 'The email or password is incorrect.']);
        $this->assertGuest();
        $this->assertSame(2, LoginAudit::query()->where('success', false)->count());
        // Emails are stored as keyed hashes, not in clear text.
        $this->assertNotSame('a@hive.test', LoginAudit::query()->first()->identifier_hash);
    }

    public function test_inactive_accounts_cannot_sign_in(): void
    {
        User::factory()->inactive()->create(['email' => 'gone@hive.test']);

        $this->post('/login', ['email' => 'gone@hive.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_sign_in_is_locked_after_five_failures(): void
    {
        User::factory()->create(['email' => 'a@hive.test']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'a@hive.test', 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'a@hive.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertTrue(LoginAudit::query()->where('reason', 'throttled')->exists());
    }

    public function test_sso_only_accounts_cannot_use_a_password(): void
    {
        $user = User::factory()->create(['email' => 'sso@hive.test']);
        $user->forceFill(['password' => null])->save();

        $this->post('/login', ['email' => 'sso@hive.test', 'password' => ''])->assertSessionHasErrors('password');
        $this->post('/login', ['email' => 'sso@hive.test', 'password' => 'anything'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_local_login_can_be_switched_off(): void
    {
        Features::set('local_login', false, null);

        $this->post('/login', ['email' => 'a@hive.test', 'password' => 'password'])->assertNotFound();
        $this->get('/login')->assertOk()->assertDontSee('name="password"', false);
    }

    public function test_redirect_after_login_stays_on_this_site(): void
    {
        User::factory()->withRole('startup_user')->create(['email' => 'f@hive.test']);

        $this->withSession(['url.intended' => 'https://evil.example/steal'])
            ->post('/login', ['email' => 'f@hive.test', 'password' => 'password'])
            ->assertRedirect('/workspace');

        $this->post('/logout');
        $this->withSession(['url.intended' => url('/news')])
            ->post('/login', ['email' => 'f@hive.test', 'password' => 'password'])
            ->assertRedirect('/news');
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
