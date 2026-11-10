<?php

namespace Tests\Feature\Ai;

use App\Ai\AiClient;
use App\Ai\AiSettings;
use App\Models\Ai\AiInteraction;
use App\Models\SiteSetting;
use App\Support\Features;
use App\Support\Settings;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    private const KEY = 'sk-ant-api03-test-key-0000000000000000wxyz';

    private FakeAiClient $fake;

    protected function setUp(): void
    {
        parent::setUp();
        config(['nsp.ai.api_key' => null, 'nsp.ai.legal_approved' => false]);
        $this->fake = new FakeAiClient;
        $this->app->instance(AiClient::class, $this->fake);
    }

    private function save(array $overrides = [])
    {
        return $this->actingAs($this->userWithRole('platform_admin'))->put('/admin/ai', $overrides + [
            'api_key' => self::KEY, 'model' => 'claude-sonnet-5-5', 'effort' => 'low',
            'limit_user' => 5, 'limit_startup' => 25, 'limit_platform' => 200,
        ]);
    }

    public function test_admin_saves_an_encrypted_key_and_settings_that_the_assistant_uses(): void
    {
        $this->save()->assertSessionHasNoErrors()->assertRedirect('/admin/ai');

        $stored = SiteSetting::query()->where('key', 'ai.api_key')->value('value');
        $this->assertNotSame(self::KEY, $stored);
        $this->assertStringNotContainsString('wxyz', $stored);
        $config = AiSettings::config();
        $this->assertSame([self::KEY, 'claude-sonnet-5-5', 'low'], [$config['api_key'], $config['model'], $config['effort']]);
        $this->assertSame(['user' => 5, 'startup' => 25, 'platform' => 200], $config['daily_limits']);
        $this->assertArrayNotHasKey('ai.api_key', Settings::all(), 'The key never reaches the branding settings shared with page views.');

        $page = $this->actingAs($this->userWithRole('platform_admin'))->get('/admin/ai')->assertOk();
        $page->assertSee('••••wxyz')->assertDontSee(self::KEY);
        $this->assertDatabaseMissing('audit_logs', ['changes' => json_encode(['key' => self::KEY])]);
    }

    public function test_leaving_the_key_empty_keeps_it_and_remove_clears_it_and_switches_off(): void
    {
        $this->save();
        $this->save(['api_key' => ''])->assertSessionHasNoErrors();
        $this->assertSame(self::KEY, AiSettings::config()['api_key']);

        Features::set('ai_assistant', true, null);
        $this->save(['api_key' => '', 'remove_key' => 1])->assertSessionHasNoErrors();
        $this->assertNull(AiSettings::config()['api_key']);
        $this->assertFalse(Features::enabled('ai_assistant'));
    }

    public function test_a_rejected_key_is_not_flashed_back_into_the_form(): void
    {
        $this->save(['api_key' => 'short key'])->assertSessionHasErrors('api_key')->assertSessionMissing('_old_input.api_key');
    }

    public function test_switching_on_needs_a_key_and_a_recorded_legal_review(): void
    {
        $admin = $this->userWithRole('platform_admin');
        $this->actingAs($admin)->post('/admin/ai/toggle', ['enabled' => 1])->assertSessionHasErrors('ai');

        $this->save();
        $this->actingAs($admin)->post('/admin/ai/toggle', ['enabled' => 1])->assertSessionHasErrors('ai');

        $this->actingAs($admin)->post('/admin/ai/legal', ['legal_reference' => 'Memo LM-14'])->assertSessionHasErrors('confirm');
        $this->actingAs($admin)->post('/admin/ai/legal', ['legal_reference' => 'Memo LM-14', 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Memo LM-14', AiSettings::legalApproval()['reference']);

        $this->actingAs($admin)->post('/admin/ai/toggle', ['enabled' => 1])->assertSessionHasNoErrors();
        $this->assertTrue(Features::enabled('ai_assistant'));

        $this->actingAs($admin)->delete('/admin/ai/legal')->assertSessionHasNoErrors();
        $this->assertFalse(Features::enabled('ai_assistant'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.legal_approval_withdrawn']);
    }

    public function test_connection_test_records_the_result(): void
    {
        $this->save();
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)->post('/admin/ai/test')->assertSessionHas('status');
        $this->assertSame('1', AiSettings::stored()['last_test_ok']);

        $this->fake->checkError = 'The API key was rejected.';
        $this->actingAs($admin)->post('/admin/ai/test')->assertSessionHasErrors('ai');
        $this->actingAs($admin)->get('/admin/ai')->assertSee('test failed');
    }

    public function test_page_shows_token_usage_and_estimated_cost(): void
    {
        AiInteraction::query()->create(['user_id' => null, 'startup_uuid' => null, 'task' => 'pitch', 'model' => 'claude-opus-5-5', 'status' => 'ok',
            'input_chars' => 10, 'output_chars' => 10, 'input_tokens' => 250000, 'output_tokens' => 50000]);

        // 250k × $4/M + 50k × $20/M = $1.00 + $1.00
        $this->actingAs($this->userWithRole('platform_admin'))->get('/admin/ai')->assertOk()
            ->assertSee('250,000')->assertSee('50,000')->assertSee('US$ 2.00');
    }

    public function test_admin_can_select_openai_and_a_supported_model(): void
    {
        $this->save(['provider' => 'openai', 'model' => 'gpt-4.1-mini'])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/ai');

        $config = AiSettings::config();
        $this->assertSame('openai', $config['provider']);
        $this->assertSame('gpt-4.1-mini', $config['model']);
        $this->assertSame(self::KEY, $config['api_key']);
        $stored = SiteSetting::query()->where('key', 'ai.api_key_openai')->value('value');
        $this->assertNotSame(self::KEY, $stored);
        $otherKey = 'sk-gemini-other-test-key-0000000000000000';
        $this->save(['provider' => 'gemini', 'model' => 'gemini-2.5-flash', 'api_key' => $otherKey])->assertSessionHasNoErrors();
        $this->assertSame($otherKey, AiSettings::config()['api_key']);
        $this->save(['provider' => 'openai', 'model' => 'gpt-4.1-mini', 'api_key' => ''])->assertSessionHasNoErrors();
        $this->assertSame(self::KEY, AiSettings::config()['api_key'], 'Provider credentials remain independent when switching providers.');
    }

    public function test_unknown_models_are_rejected_for_curated_providers(): void
    {
        $this->save(['provider' => 'gemini', 'model' => 'made-up-model'])
            ->assertSessionHasErrors('model');
    }

    public function test_custom_api_rejects_private_network_endpoints(): void
    {
        $this->save([
            'provider' => 'custom', 'model' => '', 'custom_model' => 'local-model',
            'base_url' => 'https://127.0.0.1/v1',
        ])->assertSessionHasErrors('base_url');
    }

    public function test_only_admins_who_manage_features_can_open_it(): void
    {
        $this->actingAs($this->userWithRole('startup_user'))->get('/admin/ai')->assertForbidden();
        $this->actingAs($this->userWithRole('startup_user'))->put('/admin/ai', ['model' => 'claude-opus-5-5'])->assertForbidden();
    }
}
