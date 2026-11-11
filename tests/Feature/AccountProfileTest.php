<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** "My profile": members change their own name, email address and password. */
class AccountProfileTest extends TestCase
{
    private function member(array $attributes = []): User
    {
        return User::factory()->withRole('startup_user')->create(array_merge(['password' => 'old-password-123'], $attributes));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
    }

    public function test_profile_page_shows_in_the_member_shell(): void
    {
        $user = $this->member(['name' => 'Amina Okello']);

        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertSee('My profile')
            ->assertSee('Amina Okello')
            ->assertSee('id="app-sidebar"', false)
            ->assertSee(route('account.edit'), false);
    }

    public function test_member_can_change_name_without_password(): void
    {
        $user = $this->member();

        $this->actingAs($user)->put('/account/profile', ['name' => 'New Name', 'email' => $user->email])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('New Name', $user->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.profile_updated']);
    }

    public function test_email_change_needs_current_password(): void
    {
        $user = $this->member(['email' => 'old@example.com']);

        $this->actingAs($user)->put('/account/profile', ['name' => $user->name, 'email' => 'new@example.com'])
            ->assertSessionHasErrors('email_current_password');
        $this->actingAs($user)->put('/account/profile', ['name' => $user->name, 'email' => 'new@example.com', 'email_current_password' => 'wrong-password-1'])
            ->assertSessionHasErrors('email_current_password');

        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    public function test_email_change_resets_verification_and_sends_confirmation(): void
    {
        Notification::fake();
        $user = $this->member(['email' => 'old@example.com', 'email_verified_at' => now()]);

        $this->actingAs($user)->put('/account/profile', ['name' => $user->name, 'email' => 'New@Example.com', 'email_current_password' => 'old-password-123'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_email_must_be_unique(): void
    {
        $this->member(['email' => 'taken@example.com']);
        $user = $this->member();

        $this->actingAs($user)->put('/account/profile', ['name' => $user->name, 'email' => 'taken@example.com', 'email_current_password' => 'old-password-123'])
            ->assertSessionHasErrors('email');
    }

    public function test_member_can_change_password(): void
    {
        $user = $this->member();

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'old-password-123', 'password' => 'brand-new-pass-456', 'password_confirmation' => 'brand-new-pass-456',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-pass-456', $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.password_changed']);
    }

    public function test_password_change_rejects_wrong_current_and_weak_new_password(): void
    {
        $user = $this->member();

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'not-my-password-1', 'password' => 'brand-new-pass-456', 'password_confirmation' => 'brand-new-pass-456',
        ])->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'old-password-123', 'password' => 'short1', 'password_confirmation' => 'short1',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_single_sign_on_accounts_cannot_change_email_or_password(): void
    {
        $user = $this->member();
        $user->forceFill(['password' => null])->save();

        $this->actingAs($user)->get('/account')->assertOk()->assertSee('single sign-on');
        $this->actingAs($user)->put('/account/profile', ['name' => $user->name, 'email' => 'other@example.com'])->assertForbidden();
        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'x', 'password' => 'brand-new-pass-456', 'password_confirmation' => 'brand-new-pass-456',
        ])->assertForbidden();
    }
}
