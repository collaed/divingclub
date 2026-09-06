<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $year
 * @property string|null $name
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property bool $is_active
 * @property array<int, array<string, mixed>>|null $fee_taper_tiers
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Season extends Model
{
    use HasFactory;

    protected $fillable = ['year', 'name', 'start_date', 'end_date', 'is_active', 'fee_taper_tiers'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean', 'fee_taper_tiers' => 'array'];
    }

    /**
     * Resolve the current dues/season year by comparing today against the
     * season cut-off. The cut-off is the start month of the most recent season
     * that has a start date (defaults to September). On or after that month the
     * dues year rolls over to the next calendar year. This matches the
     * membership_fees.season_year convention (e.g. a season starting Sept 2026
     * is the "2027" dues year).
     */
    public static function currentDuesYear(?Carbon $reference = null): string
    {
        $reference ??= Carbon::today();

        $latestStarted = static::query()
            ->whereNotNull('start_date')
            ->orderByDesc('start_date')
            ->first();

        $rolloverMonth = $latestStarted?->start_date?->month ?? 9;

        $year = (int) $reference->year;
        if ($reference->month >= $rolloverMonth) {
            $year++;
        }

        return (string) $year;
    }

    /**
     * Resolve the fee-taper percentage (0-100) for a reference date within this
     * season. Returns 100 when no schedule applies. Tiers are month-day anchors
     * ({"from":"MM-DD","pct":N}); the applicable percentage is that of the last
     * tier whose anchor date (mapped into the season window) is on or before the
     * reference date. A fresh season with no tiers is always 100%.
     */
    public function taperPercentage(?Carbon $reference = null): int
    {
        $tiers = $this->fee_taper_tiers;
        if (! is_array($tiers) || $tiers === []) {
            return 100;
        }

        $reference ??= Carbon::today();

        $anchor = $this->start_date ?? Carbon::parse($reference->year.'-01-01');

        $resolved = [];
        foreach ($tiers as $tier) {
            if (! isset($tier['from'], $tier['pct'])) {
                continue;
            }
            [$month, $day] = array_pad(explode('-', (string) $tier['from']), 2, '01');
            // Map the MM-DD anchor into the correct season year: if the month-day
            // is before the season start month-day, it belongs to the next calendar
            // year of the season window.
            $year = (int) $anchor->year;
            $candidate = Carbon::createFromDate($year, (int) $month, (int) $day)->startOfDay();
            if ($candidate->lt($anchor->copy()->startOfDay())) {
                $candidate->addYear();
            }
            $resolved[] = ['date' => $candidate, 'pct' => (int) $tier['pct']];
        }

        usort($resolved, fn (array $a, array $b): int => $a['date']->timestamp <=> $b['date']->timestamp);

        $pct = 100;
        foreach ($resolved as $r) {
            if ($r['date']->lte($reference->copy()->startOfDay())) {
                $pct = $r['pct'];
            }
        }

        return $pct;
    }

    /** @return HasMany<SeasonHoliday, $this> */
    public function holidays(): HasMany
    {
        return $this->hasMany(SeasonHoliday::class);
    }

    /** @return HasMany<SeasonPattern, $this> */
    public function patterns(): HasMany
    {
        return $this->hasMany(SeasonPattern::class);
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
