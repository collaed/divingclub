<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around Groq's OpenAI-compatible chat completions API.
 *
 * Used for LLM tasks where the Cloudflare Workers AI models are not good
 * enough (classification, moderation, structured extraction). Groq offers
 * fast hosted inference; the API shape matches OpenAI so any future switch
 * to another OpenAI-compatible provider only needs config changes.
 */
class LlmClient
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $baseUrl = null,
        private readonly ?string $model = null,
    ) {}

    /**
     * Whether the client is configured with an API key.
     */
    public function isConfigured(): bool
    {
        return (bool) $this->key();
    }

    private function key(): ?string
    {
        return $this->apiKey ?? config('services.groq.key');
    }

    private function url(): string
    {
        $base = rtrim($this->baseUrl ?? config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/');

        return $base.'/chat/completions';
    }

    private function model(): string
    {
        return $this->model ?? config('services.groq.model', 'openai/gpt-oss-20b');
    }

    /**
     * Send a chat completion and return the assistant's text content.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array{max_tokens?: int, temperature?: float, json?: bool, model?: string, timeout?: int}  $options
     */
    public function chat(array $messages, array $options = []): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $payload = [
            'model' => $options['model'] ?? $this->model(),
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 512,
            'reasoning_effort' => config('services.groq.reasoning_effort', 'low'),
        ];

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        if (($options['json'] ?? false) === true) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = Http::withToken((string) $this->key())
                ->timeout($options['timeout'] ?? 20)
                ->post($this->url(), $payload);

            if (! $response->successful()) {
                Log::warning('LlmClient request failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $content = $response->json('choices.0.message.content');

            return is_string($content) && $content !== '' ? $content : null;
        } catch (\Throwable $e) {
            Log::warning('LlmClient request threw', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Send a chat completion expecting a JSON object response and decode it.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array{max_tokens?: int, temperature?: float, model?: string, timeout?: int}  $options
     * @return array<string, mixed>|null
     */
    public function chatJson(array $messages, array $options = []): ?array
    {
        $options['json'] = true;
        $raw = $this->chat($messages, $options);

        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
