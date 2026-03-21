<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;

class MailAliasService
{
    /**
     * Resolve an alias to a list of email addresses.
     *
     * @return array{emails: string[], label: string}|null
     */
    public static function resolve(string $alias): ?array
    {
        $local = strtolower(explode('@', $alias)[0]);

        // Static aliases
        return match ($local) {
            'bureau', 'members.b' => static::bureau(),
            'all', 'members' => static::allActive(),
            'instructors' => static::instructors(),
            default => static::dynamic($local),
        };
    }

    /** Bureau members (bureau_member checkbox on member_details). */
    private static function bureau(): array
    {
        $emails = User::whereHas('detail', fn ($q) => $q->where('bureau_member', true))
            ->pluck('primary_email')->toArray();

        return ['emails' => $emails, 'label' => 'Bureau'];
    }

    /** All active members. */
    private static function allActive(): array
    {
        $emails = User::whereHas('status', fn ($q) => $q->whereIn('slug', ['actif', 'membre_de_droit', 'fonctionnaire']))
            ->whereNotNull('email_verified_at')
            ->pluck('primary_email')->toArray();

        return ['emails' => $emails, 'label' => 'All active members'];
    }

    /** Instructors. */
    private static function instructors(): array
    {
        $emails = User::whereHas('role', fn ($q) => $q->where('slug', 'instructor'))
            ->pluck('primary_email')->toArray();

        return ['emails' => $emails, 'label' => 'Instructors'];
    }

    /** Dynamic aliases: event-{id} → confirmed registrations. */
    private static function dynamic(string $local): ?array
    {
        if (preg_match('/^event-(\d+)$/', $local, $m)) {
            $event = Event::find((int) $m[1]);
            if (! $event) {
                return null;
            }

            $emails = $event->confirmedRegistrations()
                ->with('user')
                ->get()
                ->pluck('user.primary_email')
                ->filter()
                ->toArray();

            return ['emails' => $emails, 'label' => "Event: {$event->title}"];
        }

        return null;
    }

    /** List all known static aliases. */
    public static function staticAliases(): array
    {
        return [
            'bureau' => 'Bureau members (bureau_member flag)',
            'members.b' => 'Bureau members (alias for bureau)',
            'all' => 'All active members',
            'members' => 'All active members (alias for all)',
            'instructors' => 'All instructors',
        ];
    }
}
