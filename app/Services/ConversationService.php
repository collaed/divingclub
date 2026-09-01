<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MailAlias;
use App\Models\MailConversation;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Manages proxied conversations between Bureau members and external parties.
 *
 * A conversation mints a per-conversation SAS alias (sas+conv.{token}@domain)
 * so the external party can reply without ever seeing the member's real
 * address. Replies are matched back by token and forwarded to the initiator.
 */
class ConversationService
{
    /**
     * Start a new proxied conversation and mint its SAS alias.
     *
     * Persists the conversation, records the alias in mail_aliases, and returns
     * the conversation. The caller is responsible for sending the outbound
     * message with the dual Reply-To (SAS alias + club log mailbox).
     */
    public static function start(
        User $initiator,
        string $externalEmail,
        string $subject,
        ?int $eventId = null,
        ?string $externalName = null,
    ): MailConversation {
        $token = static::uniqueToken();
        $sasAlias = MailAliasService::mailtoAddress("conv.{$token}");

        $conversation = MailConversation::create([
            'initiator_user_id' => $initiator->id,
            'event_id' => $eventId,
            'external_email' => strtolower(trim($externalEmail)),
            'external_name' => $externalName,
            'token' => $token,
            'sas_alias' => $sasAlias,
            'subject' => $subject,
            'hit_count' => 1,
            'last_activity_at' => now(),
        ]);

        MailAlias::create([
            'user_id' => $initiator->id,
            'alias' => $sasAlias,
            'type' => 'sas_conv',
            'active' => true,
            'hit_count' => 1,
        ]);

        return $conversation;
    }

    /**
     * Resolve an inbound SAS alias (sas+conv.{token}@domain or the bare tag)
     * to its conversation, if any.
     */
    public static function matchToken(string $alias): ?MailConversation
    {
        $token = static::extractToken($alias);
        if ($token === null) {
            return null;
        }

        return MailConversation::where('token', $token)->first();
    }

    /**
     * Record activity on a conversation (an inbound reply or a follow-up send):
     * bump hit_count and refresh last_activity_at.
     */
    public static function recordActivity(MailConversation $conversation): void
    {
        $conversation->increment('hit_count');
        $conversation->forceFill(['last_activity_at' => now()])->save();
    }

    /**
     * Extract the conversation token from an address or tag.
     * Accepts: "sas+conv.abc123@clubcep.eu", "conv.abc123", "abc123".
     */
    protected static function extractToken(string $alias): ?string
    {
        $local = strtolower(explode('@', trim($alias))[0]);

        // Strip the mailbox+ prefix if present (e.g. cep+conv.abc123 -> conv.abc123)
        if (str_contains($local, '+')) {
            $local = substr($local, strpos($local, '+') + 1);
        }

        if (preg_match('/^conv\.([a-z0-9]+)$/', $local, $m)) {
            return $m[1];
        }

        if (preg_match('/^[a-z0-9]+$/', $local)) {
            return $local;
        }

        return null;
    }

    /** Generate a token that is not already in use. */
    protected static function uniqueToken(): string
    {
        do {
            $token = strtolower(Str::random(10));
        } while (MailConversation::where('token', $token)->exists());

        return $token;
    }
}
