<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    private const PASSWORD = 'CorrectHorse2026';

    public function test_creates_an_admin_who_can_sign_in(): void
    {
        $this->artisan('nsp:create-admin', ['email' => 'Owner@Example.test'])
            ->expectsQuestion('Password (at least 12 characters, letters and numbers; typing is hidden)', self::PASSWORD)
            ->expectsQuestion('Repeat the password', self::PASSWORD)
            ->assertSuccessful();

        $user = User::query()->where('email', 'owner@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('super_admin'));
        $this->post('/login', ['email' => 'owner@example.test', 'password' => self::PASSWORD])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_mismatched_passwords_change_nothing(): void
    {
        $this->artisan('nsp:create-admin', ['email' => 'owner@example.test'])
            ->expectsQuestion('Password (at least 12 characters, letters and numbers; typing is hidden)', self::PASSWORD)
            ->expectsQuestion('Repeat the password', self::PASSWORD.'x')
            ->expectsOutput('The two passwords do not match. Nothing was changed.')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.test']);
    }

    public function test_existing_account_keeps_its_password_unless_reset_is_requested(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.test', 'password' => 'OldPassword2025', 'is_active' => false]);

        $this->artisan('nsp:create-admin', ['email' => 'owner@example.test'])->assertSuccessful();
        $this->assertTrue(Hash::check('OldPassword2025', $user->fresh()->password));

        $this->artisan('nsp:create-admin', ['email' => 'owner@example.test', '--reset-password' => true])
            ->expectsQuestion('Password (at least 12 characters, letters and numbers; typing is hidden)', self::PASSWORD)
            ->expectsQuestion('Repeat the password', self::PASSWORD)
            ->expectsOutput('Password reset for owner@example.test.')
            ->assertSuccessful();

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check(self::PASSWORD, $fresh->password));
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_reset_cli']);
    }
}
