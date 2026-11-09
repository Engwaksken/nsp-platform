<?php

namespace Tests\Feature\Ai;

use App\Ai\AnthropicAiClient;
use Tests\TestCase;

/** Runs the real SDK client against a local stand-in server to check the request and response handling. */
class AnthropicClientTest extends TestCase
{
    private static $server;

    private static int $port;

    public static function setUpBeforeClass(): void
    {
        self::$port = random_int(20000, 40000);
        self::$server = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.self::$port, dirname(__DIR__, 2).'/Fixtures/anthropic-mock.php'], [['pipe', 'r'], ['file', \PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w'], ['file', \PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w']], $pipes);
        for ($i = 0; $i < 50; $i++) {
            if (@fsockopen('127.0.0.1', self::$port)) {
                return;
            }
            usleep(100000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server) {
            proc_terminate(self::$server);
        }
    }

    private function client(array $overrides = []): AnthropicAiClient
    {
        return new AnthropicAiClient($overrides + [
            'api_key' => 'sk-test-not-real', 'base_url' => 'http://127.0.0.1:'.self::$port, 'model' => 'claude-opus-5-5',
            'effort' => 'medium', 'max_tokens' => 4000, 'timeout' => 10, 'max_retries' => 0,
        ]);
    }

    private function lastRequest(): array
    {
        return json_decode((string) file_get_contents(sys_get_temp_dir().'/nsp-anthropic-mock-'.self::$port.'.json'), true);
    }

    public function test_sends_a_fixed_system_prompt_with_fallbacks_and_effort_and_reads_text(): void
    {
        $result = $this->client()->complete('FIXED SYSTEM', "Task\n<founder_text>\nhello\n</founder_text>");

        $this->assertTrue($result->ok());
        $this->assertSame('A clearer description.', $result->text);
        $this->assertSame([100, 12, 'claude-opus-5-5'], [$result->inputTokens, $result->outputTokens, $result->model]);

        $request = $this->lastRequest();
        $this->assertSame('/v1/messages', $request['path']);
        $this->assertSame('server-side-fallback-2026-07-01', $request['beta']);
        $this->assertSame('default', $request['body']['fallbacks']);
        $this->assertSame(['effort' => 'medium'], $request['body']['output_config']);
        $this->assertSame('FIXED SYSTEM', $request['body']['system']);
        $this->assertSame([['role' => 'user', 'content' => "Task\n<founder_text>\nhello\n</founder_text>"]], $request['body']['messages']);
        $this->assertArrayNotHasKey('tools', $request['body'], 'The model gets no tools.');
    }

    public function test_haiku_is_sent_without_server_side_fallbacks(): void
    {
        $this->client(['model' => 'claude-haiku-5-5'])->complete('S', 'U');

        $request = $this->lastRequest();
        $this->assertNull($request['beta']);
        $this->assertArrayNotHasKey('fallbacks', $request['body']);
        $this->assertSame('claude-haiku-5-5', $request['body']['model']);
    }

    public function test_connection_check_looks_up_the_model_and_explains_failures(): void
    {
        $this->assertNull($this->client()->check());
        $this->assertSame('/v1/models/claude-opus-5-5', $this->lastRequest()['path']);

        $this->assertStringContainsString('rejected', $this->client(['api_key' => 'bad-key'])->check());
        $this->assertStringContainsString('not available', $this->client(['model' => 'missing-model'])->check());
        $this->assertStringContainsString('could not be reached', $this->client(['base_url' => 'http://127.0.0.1:9', 'timeout' => 3])->check());
        $this->assertSame('No API key is set.', $this->client(['api_key' => null])->check());
    }

    public function test_refusal_and_errors_become_results_not_exceptions(): void
    {
        $this->assertSame('refused', $this->client()->complete('S', 'TRIGGER_REFUSAL')->status);

        $limited = $this->client()->complete('S', 'TRIGGER_429');
        $this->assertSame('error', $limited->status);
        $this->assertStringContainsString('busy', $limited->error);

        $offline = $this->client(['base_url' => 'http://127.0.0.1:9', 'timeout' => 3])->complete('S', 'U');
        $this->assertSame('error', $offline->status);
    }
}
