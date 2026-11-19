<?php

namespace Tests\Feature\Registry;

use App\Mail\AccountAlreadyExists;
use App\Models\User;
use App\Support\Features;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class FounderSignupTest extends TestCase
{
    private function signup(array $overrides = [])
    {
        return $this->post('/register', $overrides + [
            'purpose' => 'startup',
            'name' => 'Amina Founder',
            'email' => 'Amina@Startup.test',
            'password' => 'correct horse 42 battery',
            'password_confirmation' => 'correct horse 42 battery',
        ]);
    }

    public function test_home_and_login_pages_offer_sign_up(): void
    {
        $this->get('/')->assertOk()->assertSee(route('register'));
        $this->get('/login')->assertOk()->assertSee('Create an account');
        $this->get('/register')->assertOk()->assertSee('What brings you to NSP?')
            ->assertSee('I invest in startups')->assertSee('I mentor or advise startups')->assertSee('I am looking for a job');
    }

    public function test_founder_signs_up_confirms_email_and_lands_on_the_startup_form(): void
    {
        Notification::fake();

        $this->signup()->assertRedirect(route('register.sent'));
        $this->assertGuest();
        $this->get(route('register.sent'))->assertOk()->assertSee('amina@startup.test');

        $user = User::query()->where('email', 'amina@startup.test')->firstOrFail();
        $this->assertTrue($user->hasRole('startup_user'));
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);

        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($link)->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->post('/login', ['email' => 'amina@startup.test', 'password' => 'correct horse 42 battery'])
            ->assertRedirect(route('workspace.startups.create'));
        $this->get(route('workspace.startups.create'))->assertOk();
    }

    public function test_unconfirmed_accounts_cannot_create_join_or_accept(): void
    {
        $user = User::factory()->unverified()->withRole('startup_user')->create();

        $this->actingAs($user)->get(route('workspace.startups.create'))->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->post(route('workspace.startups.store'), ['legal_name' => 'X Ltd', 'display_name' => 'X', 'country' => 'UG'])
            ->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->post(route('invitations.accept', 'some-token'))->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->post(route('organizations.invitations.accept', 'some-token'))->assertRedirect(route('verification.notice'));
    }

    public function test_existing_email_gets_the_same_answer_and_no_new_account(): void
    {
        Mail::fake();
        Notification::fake();
        $existing = User::factory()->create(['email' => 'amina@startup.test', 'name' => 'Original']);

        $this->signup()->assertRedirect(route('register.sent'));

        $this->assertSame(1, User::query()->where('email', 'amina@startup.test')->count());
        $this->assertSame('Original', $existing->fresh()->name);
        $this->assertTrue($existing->fresh()->hasVerifiedEmail());
        Mail::assertQueued(AccountAlreadyExists::class, fn ($mail) => $mail->hasTo('amina@startup.test'));
        Notification::assertNothingSent();
        $this->assertGuest();
    }

    public function test_weak_or_mismatched_passwords_are_rejected(): void
    {
        $this->signup(['password' => 'short1', 'password_confirmation' => 'short1'])->assertSessionHasErrors('password');
        $this->signup(['password_confirmation' => 'something else 99'])->assertSessionHasErrors('password');
        $this->assertSame(0, User::query()->where('email', 'amina@startup.test')->count());
    }

    public function test_passwords_need_at_least_eight_characters_with_letters_and_numbers(): void
    {
        Notification::fake();

        $this->signup(['password' => 'abcdef1', 'password_confirmation' => 'abcdef1'])->assertSessionHasErrors('password');
        $this->signup(['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'])->assertSessionHasErrors('password');
        $this->signup(['password' => 'abcdefg1', 'password_confirmation' => 'abcdefg1'])->assertSessionDoesntHaveErrors();
        $this->assertTrue(User::query()->where('email', 'amina@startup.test')->exists());
    }

    public function test_sign_up_can_be_switched_off(): void
    {
        Features::set('startup_signup', false, null);

        $this->get('/register')->assertNotFound();
        $this->signup()->assertNotFound();
        $this->get('/login')->assertDontSee('Create an account');

        Features::set('startup_signup', true, null);
        Features::set('local_login', false, null);
        $this->get('/register')->assertNotFound();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function purposes(): array
    {
        return [
            'investor' => ['investor', '/investors/profile'],
            'expert' => ['expert', '/experts/profile'],
            'job seeker' => ['talent', '/talent/profile'],
        ];
    }

    /** @dataProvider purposes */
    public function test_each_kind_of_member_lands_on_their_own_profile(string $purpose, string $landing): void
    {
        Notification::fake();

        $this->signup(['purpose' => $purpose])->assertRedirect(route('register.sent'));
        $user = User::query()->where('email', 'amina@startup.test')->firstOrFail();
        $this->assertSame($purpose, $user->signup_purpose);
        // Signing up never grants a verified role; staff still verify investors and experts.
        $this->assertFalse($user->hasAnyRole(['investor', 'expert']));

        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($link)->assertRedirect(route('login'));
        $this->assertNull($user->fresh()->signup_purpose);

        $this->post('/login', ['email' => 'amina@startup.test', 'password' => 'correct horse 42 battery'])->assertRedirect(url($landing));
        $this->get($landing)->assertOk();
    }

    public function test_a_purpose_must_be_chosen_and_only_for_modules_that_are_on(): void
    {
        $this->signup(['purpose' => ''])->assertSessionHasErrors('purpose');
        $this->signup(['purpose' => 'admin'])->assertSessionHasErrors('purpose');

        Features::set('funding', false, null);
        $this->get('/register')->assertDontSee('I invest in startups');
        $this->signup(['purpose' => 'investor'])->assertSessionHasErrors('purpose');
        $this->assertFalse(User::query()->where('email', 'amina@startup.test')->exists());
    }

    public function test_each_kind_of_member_has_its_own_tab(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee('data-tabs="What brings you to NSP?"', false)
            ->assertSee('id="startup" data-tab="Startup"', false)
            ->assertSee('id="investor" data-tab="Investor"', false)
            ->assertSee('id="expert" data-tab="Expert"', false)
            ->assertSee('id="talent" data-tab="Job seeker"', false)
            ->assertSee('<input type="hidden" name="purpose" value="investor">', false);
        $this->get('/')->assertSee(route('register').'#startup', false);

        // Older ?as= links open the matching tab.
        $this->get('/register?as=expert')->assertRedirect(route('register').'#expert');
        $this->get('/register?as=nonsense')->assertRedirect(route('register'));
    }

    public function test_errors_show_only_on_the_tab_that_was_submitted(): void
    {
        $this->from('/register')->signup(['purpose' => 'investor', 'password' => 'short1', 'password_confirmation' => 'short1'])
            ->assertRedirect('/register');

        $page = $this->get('/register')->assertOk()->getContent();
        $this->assertStringContainsString('id="investor-password-error"', $page);
        $this->assertStringNotContainsString('id="startup-password-error"', $page);
        // What they typed carries over, so switching tabs does not lose it.
        $this->assertStringContainsString('value="amina@startup.test"', strtolower($page));
    }
}
