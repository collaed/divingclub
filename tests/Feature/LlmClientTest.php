<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\LlmClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.groq.key' => 'test-key',
            'services.groq.base_url' => 'https://api.groq.com/openai/v1',
            'services.groq.model' => 'openai/gpt-oss-20b',
            'services.groq.reasoning_effort' => 'low',
        ]);
    }

    public function test_is_configured_reflects_api_key(): void
    {
        $this->assertTrue((new LlmClient)->isConfigured());

        config(['services.groq.key' => null]);
        $this->assertFalse((new LlmClient)->isConfigured());
    }

    public function test_chat_returns_assistant_content(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'hello world']]],
            ], 200),
        ]);

        $result = (new LlmClient)->chat([
            ['role' => 'user', 'content' => 'hi'],
        ]);

        $this->assertSame('hello world', $result);
    }

    public function test_chat_json_decodes_object(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"ok": true, "reason": "fine"}']]],
            ], 200),
        ]);

        $result = (new LlmClient)->chatJson([
            ['role' => 'user', 'content' => 'classify'],
        ]);

        $this->assertIsArray($result);
        $this->assertTrue($result['ok']);
        $this->assertSame('fine', $result['reason']);
    }

    public function test_chat_returns_null_when_not_configured(): void
    {
        config(['services.groq.key' => null]);

        Http::fake();

        $this->assertNull((new LlmClient)->chat([
            ['role' => 'user', 'content' => 'hi'],
        ]));

        Http::assertNothingSent();
    }

    public function test_chat_returns_null_on_http_error(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['error' => 'bad'], 500),
        ]);

        $this->assertNull((new LlmClient)->chat([
            ['role' => 'user', 'content' => 'hi'],
        ]));
    }

    public function test_chat_sends_json_response_format_when_requested(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"ok": true}']]],
            ], 200),
        ]);

        (new LlmClient)->chatJson([
            ['role' => 'user', 'content' => 'classify'],
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return isset($body['response_format']['type'])
                && $body['response_format']['type'] === 'json_object'
                && $body['model'] === 'openai/gpt-oss-20b';
        });
    }
}
