<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MembershipFee;
use App\Models\MembershipFeeComponent;
use App\Models\MemberStatus;
use App\Models\Season;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds the season-2027 membership dues data:
 *  - six CEP cotisations as membership_fees keyed by member status;
 *  - four derived FFESSM licence components (official 2026/2027 tariff);
 *  - the FLASSA licence component (10€, free under 18 at the shared anchor);
 *  - seven Assurance Individuelle (Loisir) options (federal base tariff).
 *
 * The FFESSM licence band is derived from age at the shared prise-de-licence
 * anchor (Jeune = 12 to under-16, Adulte = 16+, Enfant = under-12), so a single
 * `junior` status covers the under-18 cotisation; the club's three young bands
 * (16-17 / 12-15 / <12) do not need separate statuses. See
 * .kiro/specs/membership-dues-calculation/{requirements,design}.md (R1, §3).
 *
 * Amounts are the 2027 baseline; later bureau edits win (the calculator reads
 * the DB, not this file). Idempotent — safe to re-run.
 */
class Fee2027Seeder extends Seeder
{
    private const SEASON_YEAR = '2027';

    /** CEP cotisation list prices, keyed by member-status slug. */
    private const COTISATIONS = [
        'fonctionnaire' => ['label' => 'Cotisation au CEP fonctionnaire (18 ans et plus)', 'amount' => 120.00],
        'externe' => ['label' => 'Cotisation au CEP externe (18 ans et plus)', 'amount' => 130.00],
        'actif' => ['label' => 'Cotisation au CEP externe (18 ans et plus)', 'amount' => 130.00],
        'junior' => ['label' => 'Cotisation jeune (moins de 18 ans à la prise de licence)', 'amount' => 55.00],
        'enfant' => ['label' => 'Cotisation enfant (moins de 12 ans à la prise de licence)', 'amount' => 55.00],
        'sympathisant' => ['label' => 'Cotisation sympathisant', 'amount' => 30.00],
    ];

    /** FFESSM licence components (derived, not user-selectable). */
    private const FFESSM_LICENCES = [
        ['slug' => 'lic_adulte', 'name' => 'Licence FFESSM adulte', 'amount' => 50.00, 'sort' => 10],
        ['slug' => 'lic_jeune', 'name' => 'Licence FFESSM Jeune (12 à moins de 16 ans)', 'amount' => 31.50, 'sort' => 11],
        ['slug' => 'lic_enfant', 'name' => 'Licence FFESSM Enfant', 'amount' => 14.50, 'sort' => 12],
        ['slug' => 'lic_aucune', 'name' => 'Pas de licence (sympathisant)', 'amount' => 0.00, 'sort' => 13],
    ];

    /** Assurance Individuelle (Loisir) options — federal base tariff 2026/2027. */
    private const ASSURANCES = [
        ['slug' => 'ass_loisir1', 'name' => 'Assurance Loisir 1', 'amount' => 25.00, 'sort' => 20],
        ['slug' => 'ass_loisir1top', 'name' => 'Assurance Loisir 1 Top', 'amount' => 48.00, 'sort' => 21],
        ['slug' => 'ass_loisir2', 'name' => 'Assurance Loisir 2', 'amount' => 30.00, 'sort' => 22],
        ['slug' => 'ass_loisir2top', 'name' => 'Assurance Loisir 2 Top', 'amount' => 59.50, 'sort' => 23],
        ['slug' => 'ass_loisir3', 'name' => 'Assurance Loisir 3', 'amount' => 51.00, 'sort' => 24],
        ['slug' => 'ass_loisir3top', 'name' => 'Assurance Loisir 3 Top', 'amount' => 99.00, 'sort' => 25],
        ['slug' => 'ass_aucune', 'name' => "Pas d'Assurance Loisir", 'amount' => 0.00, 'sort' => 26],
    ];

    public function run(): void
    {
        $season = $this->resolveSeason();
        $anchor = $season->start_date ?? Carbon::createFromDate((int) self::SEASON_YEAR - 1, 9, 1);

        $this->seedCotisations($season);
        $this->seedFfessmLicences($season);
        $this->seedFlassa($season, $anchor);
        $this->seedAssurances($season);
    }

    private function resolveSeason(): Season
    {
        return Season::firstOrCreate(
            ['year' => self::SEASON_YEAR],
            [
                'name' => 'Saison 2026-2027',
                'start_date' => '2026-09-01',
                'end_date' => '2027-07-31',
                'is_active' => false,
            ]
        );
    }

    private function seedCotisations(Season $season): void
    {
        $statusIdBySlug = MemberStatus::pluck('id', 'slug');

        foreach (self::COTISATIONS as $statusSlug => $def) {
            $statusId = $statusIdBySlug[$statusSlug] ?? null;
            if ($statusId === null) {
                continue;
            }

            MembershipFee::updateOrCreate(
                ['season_year' => self::SEASON_YEAR, 'status_id' => $statusId],
                [
                    'season_id' => $season->id,
                    'amount' => $def['amount'],
                    'label' => $def['label'],
                ]
            );
        }
    }

    private function seedFfessmLicences(Season $season): void
    {
        foreach (self::FFESSM_LICENCES as $lic) {
            MembershipFeeComponent::updateOrCreate(
                ['slug' => $lic['slug']],
                [
                    'season_id' => $season->id,
                    'name' => $lic['name'],
                    'kind' => MembershipFeeComponent::KIND_FFESSM_LICENCE,
                    'amount' => $lic['amount'],
                    'is_base' => false,
                    'is_optional' => false,
                    'prorata_eligible' => false,
                    'sort_order' => $lic['sort'],
                ]
            );
        }
    }

    private function seedFlassa(Season $season, Carbon $anchor): void
    {
        MembershipFeeComponent::updateOrCreate(
            ['slug' => 'flassa'],
            [
                'season_id' => $season->id,
                'name' => 'Licence FLASSA',
                'kind' => MembershipFeeComponent::KIND_FLASSA,
                'amount' => 10.00,
                'is_base' => false,
                'is_optional' => false,
                'prorata_eligible' => false,
                'taper_below_age' => 18,
                'taper_ratio' => 0,
                'age_anchor_date' => $anchor->toDateString(),
                'sort_order' => 14,
            ]
        );
    }

    private function seedAssurances(Season $season): void
    {
        foreach (self::ASSURANCES as $ass) {
            MembershipFeeComponent::updateOrCreate(
                ['slug' => $ass['slug']],
                [
                    'season_id' => $season->id,
                    'name' => $ass['name'],
                    'kind' => MembershipFeeComponent::KIND_ASSURANCE,
                    'amount' => $ass['amount'],
                    'is_base' => false,
                    'is_optional' => true,
                    'prorata_eligible' => false,
                    'sort_order' => $ass['sort'],
                ]
            );
        }
    }
}
