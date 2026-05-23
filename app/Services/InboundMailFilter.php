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
    public static function filter(string $body, ?int $eventId = null, ?string $senderEmail = null): array
    {
        $original = $body;

        // 1. Strip common email signatures
        $body = static::stripSignatures($body, $senderEmail);

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

    protected static function stripSignatures(string $body, ?string $senderEmail = null): string
    {
        // HTML payloads: use DOM parsing for surgical removal
        if (str_contains($body, '<') && str_contains($body, '>')) {
            $cleaned = static::stripSignaturesHtml($body, $senderEmail);
            if ($cleaned !== null) {
                return $cleaned;
            }
        }

        // Plain text fallback
        return static::stripSignaturesText($body, $senderEmail);
    }

    protected static function stripSignaturesHtml(string $html, ?string $senderEmail): ?string
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<meta charset="utf-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new \DOMXPath($dom);
        $severed = false;

        // 1. Sender-specific anchor via config
        if ($senderEmail) {
            $domain = strtolower(substr($senderEmail, strpos($senderEmail, '@') + 1));
            $rules = config("mail_signatures.{$domain}", []);
            $anchor = $rules['html_anchor'] ?? $rules['text_anchor'] ?? null;

            if ($anchor) {
                $nodes = $xpath->query("//*[contains(., '{$anchor}')]");
                if ($nodes && $nodes->length > 0) {
                    $sigNode = $nodes->item($nodes->length - 1);
                    static::severFromNode($sigNode);
                    $severed = true;
                }
            }
        }

        // 2. Outlook reply separator (blue border-top div)
        if (! $severed) {
            $separators = $xpath->query("//div[contains(@style, 'border-top:solid')]");
            if ($separators && $separators->length > 0) {
                static::severFromNode($separators->item(0));
                $severed = true;
            }
        }

        if (! $severed) {
            return null; // Fall through to text-based stripping
        }

        $result = $dom->saveHTML();
        $result = str_replace('<meta charset="utf-8">', '', $result);

        return trim($result);
    }

    protected static function severFromNode(\DOMNode $node): void
    {
        // Remove this node and all following siblings
        $parent = $node->parentNode;
        if (! $parent) {
            return;
        }

        while ($parent->lastChild && $parent->lastChild !== $node) {
            $parent->removeChild($parent->lastChild);
        }
        $parent->removeChild($node);
    }

    protected static function stripSignaturesText(string $body, ?string $senderEmail): string
    {
        // 1. Sender-specific signature anchors (exact match — no false positives)
        if ($senderEmail) {
            $domain = strtolower(substr($senderEmail, strpos($senderEmail, '@') + 1));
            $rules = config("mail_signatures.{$domain}", []);

            foreach (['text_anchor', 'html_anchor'] as $key) {
                $anchor = $rules[$key] ?? null;
                if ($anchor && ($pos = strpos($body, $anchor)) !== false) {
                    return trim(substr($body, 0, $pos));
                }
            }
        }

        // 2. Global device/client footers
        $globalFooters = config('mail_signatures.global_device_footers', [
            'Sent from my iPhone', 'Sent from my iPad',
            'Sent from my Galaxy', 'Sent from my Huawei',
            'Envoyé de mon iPhone', 'Envoyé de mon iPad',
            'Get Outlook for iOS', 'Get Outlook for Android',
            'Sent from Yahoo Mail', 'Sent from Outlook for',
        ]);

        foreach ($globalFooters as $footer) {
            if (($pos = stripos($body, $footer)) !== false) {
                return trim(substr($body, 0, $pos));
            }
        }

        // 3. Standard signature delimiters (fallback)
        $patterns = [
            '/\n--\s*\n.*/s',
            '/\n_{3,}\n.*/s',
            '/\nCordialement[,.]?\s*\n.*/si',
            '/\nBest regards[,.]?\s*\n.*/si',
            '/\nKind regards[,.]?\s*\n.*/si',
            '/\nMit freundlichen Grüßen[,.]?\s*\n.*/si',
        ];

        foreach ($patterns as $p) {
            $body = preg_replace($p, '', $body);
        }

        return trim($body);
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
        $openAiKey = config('services.openai.key', '');
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
