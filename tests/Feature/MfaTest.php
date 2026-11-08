<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Tests\TestCase;

class MfaTest extends TestCase
{
    private const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();
        session()->forget('mfa_verified');
    }

    private function adminWithMfa(): array
    {
        $admin = User::factory()->withRole('platform_admin')->create(['email' => 'admin@hive.test']);
        $codes = $admin->enableMfa(self::SECRET, Totp::currentStep() - 5);

        return [$admin, $codes];
    }

    private function currentCode(): string
    {
        return Totp::codeAt(self::SECRET, Totp::currentStep());
    }

    public function test_password_alone_does_not_sign_in_an_admin_with_mfa(): void
    {
        $this->adminWithMfa();

        $this->post('/login', ['email' => 'admin@hive.test', 'password' => 'password'])->assertRedirect('/login/two-factor');
        $this->assertGuest();
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_correct_code_completes_sign_in(): void
    {
        [$admin] = $this->adminWithMfa();
        $this->post('/login', ['email' => 'admin@hive.test', 'password' => 'password']);

        $this->post('/login/two-factor', ['code' => $this->currentCode()])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk();
    }

    public function test_wrong_code_is_refused_and_attempts_are_limited(): void
    {
        $this->adminWithMfa();
        $this->post('/login', ['email' => 'admin@hive.test', 'password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->post('/login/two-factor', ['code' => $this->currentCode()])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_code_cannot_be_reused(): void
    {
        $this->adminWithMfa();
        $code = $this->currentCode();
        $this->post('/login', ['email' => 'admin@hive.test', 'password' => 'password']);
        $this->post('/login/two-factor', ['code' => $code])->assertRedirect('/admin');
        $this->post('/logout');

        $this->post('/login', ['email' => 'admin@hive.test', 'password' => 'password']);
        $this->post('/login/two-factor', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_works_once(): void
    {
        [$admin, $codes] = $this->adminWithMfa();
        $this->post('/login', ['email' => 'admin@hive.test', 'password' => 'password']);
        $this->post('/login/two-factor', ['code' => strtolower($codes[0])])->assertRedirect('/admin');
        $this->assertSame(7, $admin->fresh()->recoveryCodesLeft());
        $this->post('/logout');

        $this->post('/login', ['email' => 'admin@hive.test', 'password' => 'password']);
        $this->post('/login/two-factor', ['code' => $codes[0]])->assertSessionHasErrors('code');
    }

    public function test_pending_sign_in_expires(): void
    {
        $this->adminWithMfa();
        $this->post('/login', ['email' => 'admin@hive.test', 'password' => 'password']);
        $this->travel(6)->minutes();

        $this->post('/login/two-factor', ['code' => $this->currentCode()])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_without_mfa_must_enrol_before_using_admin(): void
    {
        User::factory()->withRole('content_editor')->create(['email' => 'editor@hive.test']);

        $this->post('/login', ['email' => 'editor@hive.test', 'password' => 'password'])->assertRedirect('/account/security');
        $this->get('/admin')->assertRedirect('/account/security');
        $this->get('/account/security')->assertOk()->assertSee('<svg', false);

        $secret = session('mfa_setup_secret');
        $this->post('/account/security/enable', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->post('/account/security/enable', ['code' => Totp::codeAt($secret, Totp::currentStep())])
            ->assertRedirect('/account/security')->assertSessionHas('recovery_codes');

        $this->get('/admin')->assertOk();
        $this->assertNotNull(User::query()->where('email', 'editor@hive.test')->first()->mfa_enabled_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.mfa_enabled']);
    }

    public function test_secret_is_encrypted_at_rest(): void
    {
        [$admin] = $this->adminWithMfa();

        $raw = \DB::table('users')->where('id', $admin->id)->value('mfa_secret');
        $this->assertNotSame(self::SECRET, $raw);
        $this->assertStringNotContainsString(self::SECRET, (string) $raw);
    }

    public function test_required_mfa_cannot_be_turned_off_but_super_admin_can_reset_it(): void
    {
        [$admin] = $this->adminWithMfa();
        $this->actingAs($admin)->withSession(['mfa_verified' => true])
            ->post('/account/security/disable', ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertTrue($admin->fresh()->hasMfa());

        $this->actingAs($this->userWithRole('super_admin'))->withSession(['mfa_verified' => true])
            ->post("/admin/users/{$admin->id}/reset-mfa")->assertRedirect();
        $this->assertFalse($admin->fresh()->hasMfa());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.mfa_reset']);
    }

    public function test_platform_admin_cannot_reset_a_super_admin(): void
    {
        $super = $this->userWithRole('super_admin');
        $super->enableMfa(self::SECRET, 1);

        $this->actingAs($this->userWithRole('platform_admin'))->withSession(['mfa_verified' => true])
            ->post("/admin/users/{$super->id}/reset-mfa")->assertForbidden();
        $this->assertTrue($super->fresh()->hasMfa());
    }

    public function test_startup_users_are_not_forced_to_use_mfa(): void
    {
        User::factory()->withRole('startup_user')->create(['email' => 'founder@hive.test']);

        $this->post('/login', ['email' => 'founder@hive.test', 'password' => 'password'])->assertRedirect('/workspace');
    }

    public function test_session_without_second_factor_cannot_use_admin(): void
    {
        [$admin] = $this->adminWithMfa();

        $this->actingAs($admin)->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_any_user_who_turned_on_mfa_is_challenged(): void
    {
        $founder = User::factory()->withRole('startup_user')->create(['email' => 'founder@hive.test']);
        $founder->enableMfa(self::SECRET, Totp::currentStep() - 5);

        $this->post('/login', ['email' => 'founder@hive.test', 'password' => 'password'])->assertRedirect('/login/two-factor');
        $this->assertGuest();
        $this->post('/login/two-factor', ['code' => $this->currentCode()]);
        $this->assertAuthenticatedAs($founder);
    }
}
