<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Season;
use Carbon\Carbon;
use Tests\TestCase;

class FeeTaperTest extends TestCase
{
    private function season(?array $tiers): Season
    {
        $s = new Season([
            'year' => '2026',
            'name' => 'Season 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'fee_taper_tiers' => $tiers,
        ]);
        // Ensure casts apply for start_date/end_date when set via array.
        $s->setRawAttributes([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'fee_taper_tiers' => $tiers === null ? null : json_encode($tiers),
        ]);

        return $s;
    }

    public function test_no_tiers_is_full_rate(): void
    {
        $s = $this->season(null);
        $this->assertSame(100, $s->taperPercentage(Carbon::parse('2026-05-01')));
    }

    public function test_before_first_cutoff_is_full_rate(): void
    {
        $s = $this->season([['from' => '04-01', 'pct' => 50], ['from' => '08-01', 'pct' => 100]]);
        $this->assertSame(100, $s->taperPercentage(Carbon::parse('2026-03-31')));
    }

    public function test_between_cutoffs_is_reduced(): void
    {
        $s = $this->season([['from' => '04-01', 'pct' => 50], ['from' => '08-01', 'pct' => 100]]);
        $this->assertSame(50, $s->taperPercentage(Carbon::parse('2026-04-01')));
        $this->assertSame(50, $s->taperPercentage(Carbon::parse('2026-07-31')));
    }

    public function test_after_last_cutoff_returns_to_full(): void
    {
        $s = $this->season([['from' => '04-01', 'pct' => 50], ['from' => '08-01', 'pct' => 100]]);
        $this->assertSame(100, $s->taperPercentage(Carbon::parse('2026-08-01')));
        $this->assertSame(100, $s->taperPercentage(Carbon::parse('2026-12-15')));
    }

    public function test_ceil_rounding_semantics(): void
    {
        // Documents the rounding rule used by FeeCalculationService: round up.
        $this->assertSame(53, (int) ceil(105 * 50 / 100));
        $this->assertSame(28, (int) ceil(55 * 50 / 100));
        $this->assertSame(55, (int) ceil(110 * 50 / 100));
    }
}
