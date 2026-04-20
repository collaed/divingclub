<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;

class MailAliasService
{
    /**
     * Resolve an alias to a list of email addresses.
     *
     * Accepts both formats:
     *   - Legacy: bureau@domain, event-42@domain, members.s42@domain
     *   - Plus-addressing: cep+bureau@domain, cep+event.42@domain, cep+instructors@domain
     *
     * @return array{emails: string[], label: string, auth_level: string}|null
     */
    public static function resolve(string $alias): ?array
    {
        // Extract the routing part from the address
        $local = strtolower(trim(explode('@', $alias)[0]));

        // Plus-addressing: extract tag after +
        if (str_contains($local, '+')) {
            $local = substr($local, strpos($local, '+') + 1);
            // Normalize: event.42 → event-42, members.s42 stays
            $local = preg_replace('/^event\.(\d+)$/', 'event-$1', $local);
        }

        return match (true) {
            in_array($local, ['bureau', 'members.b']) => static::bureau(),
            in_array($local, ['all', 'members']) => static::allActive(),
            in_array($local, ['instructors', 'moniteurs', 'members.m']) => static::instructors(),
            str_starts_with($local, 'event-') => static::eventParticipants($local),
            str_starts_with($local, 'members.s') => static::eventParticipantsLegacy($local),
            str_starts_with($local, 'year=') => static::membersByYear($local),
            str_starts_with($local, 'members.pn') => static::trainingLevel($local),
            default => null,
        };
    }

    /**
     * Generate a mailto address for a given alias tag.
     * e.g. mailtoAddress('event.42') → 'cep+event.42@clubcep.eu'
     */
    public static function mailtoAddress(string $tag): string
    {
        $base = config('club.mail_address', 'club@example.com');
        [$mailbox, $domain] = explode('@', $base, 2);

        return "{$mailbox}+{$tag}@{$domain}";
    }

    /**
     * Generate a mailto address for an event.
     * e.g. eventMailto(42) → 'cep+event.42@clubcep.eu'
     */
    public static function eventMailto(int $eventId): string
    {
        return static::mailtoAddress("event.{$eventId}");
    }

    /**
     * Resolve multiple aliases from a comma-separated string or array.
     * Deduplicates emails across all resolved groups.
     */
    public static function resolveMultiple(array|string $aliases): array
    {
        if (is_string($aliases)) {
            $aliases = array_map('trim', explode(',', $aliases));
        }

        $allEmails = [];
        $labels = [];
        $authLevel = 'bureau';

        foreach ($aliases as $alias) {
            $alias = trim($alias);
            if (empty($alias)) {
                continue;
            }

            // Direct email address
            if (filter_var($alias, FILTER_VALIDATE_EMAIL)) {
                $allEmails[] = $alias;
                $labels[] = $alias;

                continue;
            }

            // Name lookup (e.g. "Michel B" → find by first/last name)
            if (! str_contains($alias, '@') && ! str_contains($alias, '=') && ! str_starts_with($alias, 'members') && ! in_array($alias, ['bureau', 'instructors', 'moniteurs', 'all'])) {
                $found = static::findByName($alias);
                if ($found) {
                    $allEmails = array_merge($allEmails, $found);
                    $labels[] = $alias;
                }

                continue;
            }

            $resolved = static::resolve($alias);
            if ($resolved) {
                $allEmails = array_merge($allEmails, $resolved['emails']);
                $labels[] = $resolved['label'];
                if ($resolved['auth_level'] === 'participant') {
                    $authLevel = 'bureau_or_instructor';
                }
            }
        }

        $allEmails = array_values(array_unique(array_filter($allEmails)));

        return [
            'emails' => $allEmails,
            'label' => implode(' + ', $labels),
            'auth_level' => $authLevel,
        ];
    }

    /**
     * Check if a sender email is authorized to send to the given alias.
     */
    public static function isAuthorized(string $senderEmail, string $alias): bool
    {
        $resolved = static::resolve($alias);
        if (! $resolved) {
            return false;
        }

        $sender = User::where('primary_email', $senderEmail)->first();
        if (! $sender) {
            return false;
        }

        return match ($resolved['auth_level']) {
            'bureau' => $sender->isBureau(),
            'bureau_or_instructor' => $sender->isBureau() || $sender->hasRole('instructor'),
            'participant' => static::isEventParticipantOrStaff($sender, $alias),
            default => false,
        };
    }

    /** Bureau members (detail.bureau_member = true). */
    protected static function bureau(): array
    {
        $emails = User::whereHas('detail', fn ($q) => $q->where('bureau_member', true))
            ->pluck('primary_email')->toArray();

        return ['emails' => $emails, 'label' => 'Bureau', 'auth_level' => 'bureau'];
    }

    /** All active members with verified email. */
    protected static function allActive(): array
    {
        $emails = User::whereHas('status', fn ($q) => $q->whereIn('slug', ['actif', 'membre_de_droit', 'fonctionnaire']))
            ->whereNotNull('email_verified_at')
            ->pluck('primary_email')->toArray();

        return ['emails' => $emails, 'label' => 'All active members', 'auth_level' => 'bureau'];
    }

    /** Active instructors (detail.active_instructor = true). */
    protected static function instructors(): array
    {
        $emails = User::whereHas('detail', fn ($q) => $q->where('active_instructor', true))
            ->pluck('primary_email')->toArray();

        return ['emails' => $emails, 'label' => 'Instructors', 'auth_level' => 'bureau_or_instructor'];
    }

    /** Event participants: event-{id} format. */
    protected static function eventParticipants(string $local): ?array
    {
        if (! preg_match('/^event-(\d+)$/', $local, $m)) {
            return null;
        }

        return static::resolveEventParticipants((int) $m[1]);
    }

    /** Legacy event participants: members.s{id} format (old CEP system). */
    protected static function eventParticipantsLegacy(string $local): ?array
    {
        if (! preg_match('/^members\.s(\d+)$/', $local, $m)) {
            return null;
        }

        return static::resolveEventParticipants((int) $m[1]);
    }

    /** Resolve event participants by event ID. */
    protected static function resolveEventParticipants(int $eventId): ?array
    {
        $event = Event::find($eventId);
        if (! $event) {
            return null;
        }

        $emails = $event->confirmedRegistrations()
            ->with('user')
            ->get()
            ->pluck('user.primary_email')
            ->filter()
            ->toArray();

        // Also include instructor and responsible
        if ($event->instructor?->primary_email) {
            $emails[] = $event->instructor->primary_email;
        }
        if ($event->responsible?->primary_email) {
            $emails[] = $event->responsible->primary_email;
        }

        return [
            'emails' => array_values(array_unique($emails)),
            'label' => "Event: {$event->title}",
            'auth_level' => 'participant',
        ];
    }

    /** Members filtered by dues year. */
    protected static function membersByYear(string $local): ?array
    {
        if (! preg_match('/^year=(\d{4})$/', $local, $m)) {
            return null;
        }

        $year = $m[1];
        $emails = User::whereHas('detail', fn ($q) => $q->whereJsonContains('cotisation_years', $year))
            ->whereNotNull('email_verified_at')
            ->pluck('primary_email')->toArray();

        return ['emails' => $emails, 'label' => "Members {$year}", 'auth_level' => 'bureau'];
    }

    /** Members enrolled in a specific training level (pn1, pn2, pn3). */
    protected static function trainingLevel(string $local): ?array
    {
        if (! preg_match('/^members\.pn(\d)$/', $local, $m)) {
            return null;
        }

        $level = 'N'.$m[1];
        $emails = User::whereHas('detail', fn ($q) => $q->whereJsonContains('training_enrollments', $level))
            ->whereNotNull('email_verified_at')
            ->pluck('primary_email')->toArray();

        return ['emails' => $emails, 'label' => "Training {$level}", 'auth_level' => 'bureau_or_instructor'];
    }

    /** Find members by partial name match (first + last). */
    protected static function findByName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);
        $first = $parts[0] ?? '';
        $last = $parts[1] ?? '';

        $query = User::whereHas('detail', function ($q) use ($first, $last) {
            if ($last) {
                $q->where('first_name', 'like', "{$first}%")
                    ->where('last_name', 'like', "{$last}%");
            } else {
                $q->where(function ($w) use ($first) {
                    $w->where('first_name', 'like', "{$first}%")
                        ->orWhere('last_name', 'like', "{$first}%");
                });
            }
        });

        return $query->pluck('primary_email')->toArray();
    }

    /** Check if sender is a participant, instructor, or bureau for an event alias. */
    protected static function isEventParticipantOrStaff(User $sender, string $alias): bool
    {
        if ($sender->isBureau() || $sender->hasRole('instructor')) {
            return true;
        }

        $local = strtolower(explode('@', $alias)[0]);
        $eventId = null;
        if (preg_match('/^event-(\d+)$/', $local, $m)) {
            $eventId = (int) $m[1];
        } elseif (preg_match('/^members\.s(\d+)$/', $local, $m)) {
            $eventId = (int) $m[1];
        }

        if ($eventId) {
            $event = Event::find($eventId);

            return $event && $event->confirmedRegistrations()
                ->where('user_id', $sender->id)
                ->exists();
        }

        return false;
    }

    /** List all known static aliases for the admin guide. */
    public static function staticAliases(): array
    {
        $ex = fn ($tag) => static::mailtoAddress($tag);

        return [
            'bureau' => 'Bureau members — '.$ex('bureau'),
            'instructors' => 'Active instructors — '.$ex('instructors'),
            'members' => 'All active members — '.$ex('members'),
            'event.{id}' => 'Event participants — '.$ex('event.{id}'),
            'members.pn1' => 'N1 students — '.$ex('members.pn1'),
            'members.pn2' => 'N2 students — '.$ex('members.pn2'),
            'members.pn3' => 'N3 students — '.$ex('members.pn3'),
            'year={YYYY}' => 'Members by dues year — '.$ex('year={YYYY}'),
        ];
    }
}
