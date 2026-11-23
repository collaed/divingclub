<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MemberStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The checkbox registry behind the admin mail-alias builder
 * (Admin\MailAliasController) — each selected key ANDs one more condition
 * onto the query, so ticking e.g. "current_member" + "pn2" means both at
 * once, not either. See MailAliasDefinition.
 */
class MailAliasCriteria
{
    /**
     * de_droit = MemberStatus::FEE_ALIASES ("pays the same cotisation as
     * Membre de droit") plus the status they map to — excludes externe/actif
     * and sympathisant/honoraire/former.
     *
     * @var array<int, string>
     */
    private const DE_DROIT_SLUGS = ['membre_de_droit', 'famille', 'associe', 'assimile', 'fonctionnaire'];

    /** @return array<string, string> checkbox key => label, for the builder view */
    public static function options(): array
    {
        return [
            'current_member' => 'Current member (cotisation paid this season, or honoraire)',
            'sympathisant' => 'Sympathisant',
            'de_droit' => 'Membre de droit (incl. Famille/Associé/Assimilé/Fonctionnaire)',
            'late_payer' => "Paid last season but hasn't renewed yet (potential late payer)",
            'bureau' => 'Bureau member',
            'instructor' => 'Active instructor',
            'lifeguard' => 'Lifeguard',
            'pn1' => 'N1 (PN1)',
            'pn2' => 'N2 (PN2)',
            'pn3' => 'N3 (PN3)',
            'rifap' => 'RIFAP',
            'nitrox' => 'NITROX',
            'qualifs' => 'QUALIFS',
        ];
    }

    /**
     * Users matching every selected criterion (AND'd), plus anyone explicitly
     * included by id, minus anyone explicitly excluded by id — so a group can
     * mix "everyone who currently matches" with named exceptions, or (with no
     * criteria at all) be built from named people only.
     *
     * @param  array<int, string>  $keys
     * @param  array<int, int>  $includeIds
     * @param  array<int, int>  $excludeIds
     * @return Builder<User>
     */
    public static function matchingUsers(array $keys, array $includeIds = [], array $excludeIds = []): Builder
    {
        $query = User::query();
        $selected = array_intersect($keys, array_keys(self::options()));

        $query->where(function (Builder $q) use ($selected, $includeIds): void {
            foreach ($selected as $key) {
                self::apply($q, $key);
            }

            if ($includeIds !== []) {
                $selected !== [] ? $q->orWhereIn('id', $includeIds) : $q->whereIn('id', $includeIds);
            } elseif ($selected === []) {
                $q->whereRaw('1 = 0');
            }
        });

        if ($excludeIds !== []) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query;
    }

    /** @param  Builder<User>  $query */
    private static function apply(Builder $query, string $key): void
    {
        match ($key) {
            'current_member' => $query->active(),
            'sympathisant' => $query->whereHas('status', fn ($q) => $q->where('slug', 'sympathisant')),
            'de_droit' => $query->whereHas('status', fn ($q) => $q->whereIn('slug', self::DE_DROIT_SLUGS)),
            'late_payer' => $query->where(fn ($q) => $q
                ->whereHas('detail', fn ($d) => $d->whereJsonContains('cotisation_years', self::lastSeasonLabel()))
                ->whereDoesntHave('status', fn ($s) => $s->where('slug', 'honoraire'))
            )->whereNot(fn ($q) => $q->active()),
            'bureau' => $query->whereHas('detail', fn ($q) => $q->where('bureau_member', true)),
            'instructor' => $query->whereHas('detail', fn ($q) => $q->where('active_instructor', true)),
            'lifeguard' => $query->whereHas('detail', fn ($q) => $q->where('is_lifeguard', true)),
            'pn1' => $query->whereHas('detail', fn ($q) => $q->whereJsonContains('training_enrollments', 'N1')),
            'pn2' => $query->whereHas('detail', fn ($q) => $q->whereJsonContains('training_enrollments', 'N2')),
            'pn3' => $query->whereHas('detail', fn ($q) => $q->whereJsonContains('training_enrollments', 'N3')),
            'rifap' => $query->whereHas('detail', fn ($q) => $q->whereJsonContains('training_enrollments', 'RIFAP')),
            'nitrox' => $query->whereHas('detail', fn ($q) => $q->whereJsonContains('training_enrollments', 'NITROX')),
            'qualifs' => $query->whereHas('detail', fn ($q) => $q->whereJsonContains('training_enrollments', 'QUALIFS')),
            default => null,
        };
    }

    /** The season label just before the current valid window — see User::validCotisationYearLabels(). */
    private static function lastSeasonLabel(): string
    {
        return (string) (((int) now()->year) - 1);
    }
}
