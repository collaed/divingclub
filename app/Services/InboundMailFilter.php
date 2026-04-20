<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\Http;

/**
 * Cleans inbound email content before appending to event communications.
 *
 * Strips signatures, quoted replies, and optionally uses AI to filter
 * private/irrelevant content. If uncertain, flags for bureau approval.
 */
class InboundMailFilter
{
    /**
     * Clean an email body for event communication display.
     *
     * @return array{body: string, needs_review: bool, review_reason: ?string}
     */
    public static function filter(string $body, ?int $eventId = null): array
    {
        $original = $body;

        // 1. Strip common email signatures
        $body = static::stripSignatures($body);

        // 2. Strip quoted replies ("> " lines, "On ... wrote:" blocks)
        $body = static::stripQuotedReplies($body);

        // 3. Strip corporate disclaimers
        $body = static::stripDisclaimers($body);

        // 4. If AI is configured, check for private/irrelevant content
        $aiResult = static::aiFilter($body, $eventId);
        if ($aiResult['needs_review']) {
            return [
                'body' => $body,
                'needs_review' => true,
                'review_reason' => $aiResult['reason'],
            ];
        }

        // 5. If body is too short after cleaning, flag for review
        $textOnly = strip_tags($body);
        if (mb_strlen(trim($textOnly)) < 10 && mb_strlen(strip_tags($original)) > 50) {
            return [
                'body' => $original,
                'needs_review' => true,
                'review_reason' => 'Content too short after filtering — may have stripped too much.',
            ];
        }

        return ['body' => $body, 'needs_review' => false, 'review_reason' => null];
    }

    protected static function stripSignatures(string $body): string
    {
        // Common signature markers
        $patterns = [
            '/--\s*\n.*/s',                          // "-- \n" standard sig separator
            '/\n_{3,}\n.*/s',                         // "___" line
            '/\nCordialement[,.]?\n.*/si',            // French
            '/\nBest regards[,.]?\n.*/si',            // English
            '/\nKind regards[,.]?\n.*/si',
            '/\nMit freundlichen Grüßen[,.]?\n.*/si', // German
            '/\nSent from my (iPhone|iPad|Galaxy|Huawei|Pixel).*/si',
            '/\nEnvoyé de mon (iPhone|iPad).*/si',
        ];

        foreach ($patterns as $p) {
            $body = preg_replace($p, '', $body);
        }

        return $body;
    }

    protected static function stripQuotedReplies(string $body): string
    {
        // "On DATE, NAME wrote:" block and everything after
        $body = preg_replace('/\n(Le |On |Am ).+?(a écrit|wrote|schrieb)\s*:\s*\n.*/s', '', $body);

        // HTML quoted blocks
        $body = preg_replace('/<blockquote[^>]*>.*?<\/blockquote>/si', '', $body);

        // "> " quoted lines (keep first 2 lines for context, strip rest)
        $lines = explode("\n", $body);
        $quotedCount = 0;
        $result = [];
        foreach ($lines as $line) {
            if (preg_match('/^>/', $line)) {
                $quotedCount++;
                if ($quotedCount <= 2) {
                    $result[] = $line;
                }
            } else {
                $quotedCount = 0;
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }

    protected static function stripDisclaimers(string $body): string
    {
        // Corporate email disclaimers
        $patterns = [
            '/\n(This email|Ce message|Diese E-Mail).*(confidential|confidentiel|vertraulich).*/si',
            '/\n(DISCLAIMER|AVERTISSEMENT|HAFTUNGSAUSSCHLUSS).*/si',
            '/\nEuropean Commission.*(disclaimer|legal notice).*/si',
            '/\nCe courriel et ses annexes.*/si',
        ];

        foreach ($patterns as $p) {
            $body = preg_replace($p, '', $body);
        }

        return $body;
    }

    /**
     * Optional AI filtering via configured LLM endpoint.
     * Returns needs_review=true if content seems private or irrelevant.
     */
    protected static function aiFilter(string $body, ?int $eventId): array
    {
        $oneMinKey = config('services.onemin.key');
        $openAiKey = config('services.openai.key') ?: env('OPENAI_API_KEY');
        $apiKey = $oneMinKey ?: $openAiKey;

        if (! $apiKey || mb_strlen(strip_tags($body)) < 30) {
            return ['needs_review' => false, 'reason' => null];
        }

        $eventContext = '';
        if ($eventId) {
            $event = Event::find($eventId);
            if ($event) {
                $eventContext = "Event: {$event->title} on {$event->event_date->format('d/m/Y')} at {$event->location}.";
            }
        }

        $systemPrompt = "You are a content moderator for a diving club's event communication board. {$eventContext} Respond with JSON only: {\"ok\": true/false, \"reason\": \"...\"}. Return ok=false if the message contains: private workplace information, personal complaints not related to the event, content that should have been a private reply, or sensitive personal data. Return ok=true if it's about event organization, logistics, participation, diving conditions, or general club matters.";
        $userContent = mb_substr(strip_tags($body), 0, 500);

        try {
            if ($oneMinKey) {
                $response = Http::withHeaders(['API-KEY' => $oneMinKey])->timeout(15)
                    ->post('https://api.1min.ai/api/features', [
                        'type' => 'CHAT_WITH_AI',
                        'model' => 'gpt-4o-mini',
                        'promptObject' => ['prompt' => "{$systemPrompt}\n\nMessage:\n{$userContent}", 'isMixed' => false],
                    ]);
                $result = $response->json('aiRecord.aiRecordDetail.resultObject.0', '');
            } else {
                $response = Http::withToken($openAiKey)->timeout(10)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => 'gpt-4o-mini',
                        'max_tokens' => 100,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $userContent],
                        ],
                    ]);
                $result = $response->json('choices.0.message.content', '');
            }

            $json = json_decode($result, true);
            if (isset($json['ok']) && $json['ok'] === false) {
                return ['needs_review' => true, 'reason' => $json['reason'] ?? 'AI flagged as potentially private/irrelevant'];
            }
        } catch (\Throwable) {
            // AI unavailable — don't block
        }

        return ['needs_review' => false, 'reason' => null];
    }
}
