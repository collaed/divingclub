<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;

class MailAliasService
{
    /**
     * Resolve an alias to a list of email addresses.
     *
     * @return array{emails: string[], label: string, auth_level: string}|null
     */
    public static function resolve(string $alias): ?array
    {
        $local = strtolower(explode('@', $alias)[0]);

        return match ($local) {
            'bureau', 'members.b' => static::bureau(),
            'all', 'members' => static::allActive(),
            'instructors' => static::instructors(),
            default => static::dynamic($local),
        };
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
            return false; // unknown sender — reject
        }

        return match ($resolved['auth_level']) {
            'bureau' => $sender->isBureau(),
            'bureau_or_instructor' => $sender->isBureau() || $sender->hasRole('instructor'),
            'participant' => static::isEventParticipantOrStaff($sender, $alias),
            default => false,
        };
    }

    /** Bureau members. */
    private static function bureau(): array
    {
        $emails = User::whereHas('detail', fn ($q) => $q->where('bureau_member', true))
            ->pluck('primary_email')->toArray();

        return ['emails' => $emails, 'label' => 'Bureau', 'auth_level' => 'bureau'];
    }

    /** All active members — bureau only. */
    private static function allActive(): array
    {
        $emails = User::whereHas('status', fn ($q) => $q->whereIn('slug', ['actif', 'membre_de_droit', 'fonctionnaire']))
            ->whereNotNull('email_verified_at')
            ->pluck('primary_email')->toArray();

        return ['emails' => $emails, 'label' => 'All active members', 'auth_level' => 'bureau'];
    }

    /** Instructors — bureau or instructors can send. */
    private static function instructors(): array
    {
        $emails = User::role('instructor')
            ->pluck('primary_email')->toArray();

        return ['emails' => $emails, 'label' => 'Instructors', 'auth_level' => 'bureau_or_instructor'];
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

            return ['emails' => $emails, 'label' => "Event: {$event->title}", 'auth_level' => 'participant'];
        }

        return null;
    }

    /** Check if sender is a participant, instructor, or bureau for an event alias. */
    private static function isEventParticipantOrStaff(User $sender, string $alias): bool
    {
        if ($sender->isBureau() || $sender->hasRole('instructor')) {
            return true;
        }

        $local = strtolower(explode('@', $alias)[0]);
        if (preg_match('/^event-(\d+)$/', $local, $m)) {
            $event = Event::find((int) $m[1]);

            return $event && $event->confirmedRegistrations()
                ->where('user_id', $sender->id)
                ->exists();
        }

        return false;
    }

    /** List all known static aliases. */
    public static function staticAliases(): array
    {
        return [
            'bureau' => 'Bureau members (bureau only)',
            'members.b' => 'Bureau members (alias)',
            'all' => 'All active members (bureau only)',
            'members' => 'All active members (alias)',
            'instructors' => 'Instructors (bureau + instructors)',
        ];
    }
}
