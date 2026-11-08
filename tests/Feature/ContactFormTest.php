<?php

namespace Tests\Feature;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    private function input(array $overrides = []): array
    {
        return array_merge([
            'topic' => 'partnership', 'name' => 'Amina', 'email' => 'amina@example.org',
            'subject' => 'Partnering', 'message' => 'We would like to partner with Hive.', 'consent' => '1', 'website' => '',
        ], $overrides);
    }

    /** The form records when it was shown; submissions faster than 3 seconds are refused. */
    private function startedAgo(int $seconds): array
    {
        return ['contact_form_started' => now()->subSeconds($seconds)->timestamp];
    }

    public function test_message_is_stored_and_routed_by_topic(): void
    {
        Mail::fake();
        Settings::put(['contact_email' => 'info@hive.test', 'contact_routing' => json_encode(['partnership' => 'partners@hive.test'])]);

        $this->withSession($this->startedAgo(10))->post('/contact', $this->input())->assertRedirect('/contact');

        $message = ContactMessage::query()->firstOrFail();
        $this->assertSame('partners@hive.test', $message->routed_to);
        $this->assertTrue($message->consent_given);
        $this->assertNotSame('127.0.0.1', $message->ip_hash);
        Mail::assertQueued(ContactMessageReceived::class, fn ($mail) => $mail->hasTo('partners@hive.test'));
    }

    public function test_consent_is_required(): void
    {
        $this->withSession($this->startedAgo(10))->post('/contact', $this->input(['consent' => null]))->assertSessionHasErrors('consent');
        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_honeypot_and_too_fast_submissions_are_refused(): void
    {
        $this->withSession($this->startedAgo(10))->post('/contact', $this->input(['website' => 'http://spam']))->assertSessionHasErrors('website');
        $this->withSession($this->startedAgo(1))->post('/contact', $this->input())->assertSessionHasErrors('message');
        $this->post('/contact', $this->input())->assertSessionHasErrors('message');
        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_submissions_are_rate_limited(): void
    {
        Mail::fake();
        for ($i = 0; $i < 3; $i++) {
            $this->withSession($this->startedAgo(10))->post('/contact', $this->input())->assertRedirect();
        }
        $this->withSession($this->startedAgo(10))->post('/contact', $this->input())->assertStatus(429);
    }

    public function test_support_role_can_read_and_close_messages_and_reads_are_audited(): void
    {
        $message = ContactMessage::query()->create($this->input() + ['consent_given' => true, 'consent_text_version' => 'v1', 'status' => 'new']);
        $support = $this->userWithRole('support');

        $this->actingAs($support)->get("/admin/messages/{$message->id}")->assertOk()->assertSee('Partnering');
        $this->actingAs($support)->patch("/admin/messages/{$message->id}", ['status' => 'closed'])->assertRedirect();

        $this->assertSame('closed', $message->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'contact_message.viewed', 'actor_id' => $support->id]);
    }
}
