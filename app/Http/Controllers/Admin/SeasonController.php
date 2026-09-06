<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSeasonHolidayRequest;
use App\Http\Requests\StoreSeasonPatternRequest;
use App\Http\Requests\StoreSeasonRequest;
use App\Models\Event;
use App\Models\Season;
use App\Models\SeasonHoliday;
use App\Models\SeasonPattern;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeasonController extends Controller
{
    public function index(): RedirectResponse|View
    {
        $seasons = Season::withCount('events')->orderByDesc('year')->get();

        return view('admin.seasons.index', compact('seasons'));
    }

    public function create(): JsonResponse|RedirectResponse|View
    {
        $previousSeasons = Season::orderByDesc('year')->get();

        return view('admin.seasons.form', ['season' => new Season, 'previousSeasons' => $previousSeasons]);
    }

    public function store(StoreSeasonRequest $request): JsonResponse|RedirectResponse|View
    {
        $v = $request->validated();

        $season = Season::create($v);

        // Clone from previous season if requested
        if ($request->filled('clone_from')) {
            $source = Season::with(['holidays', 'patterns'])->find($request->clone_from);
            if ($source) {
                $yearDiff = $v['year'] - $source->year;
                foreach ($source->holidays as $h) {
                    SeasonHoliday::create([
                        'season_id' => $season->id,
                        'name' => $h->name,
                        'start_date' => $h->start_date->addYears($yearDiff),
                        'end_date' => $h->end_date->addYears($yearDiff),
                        'is_adhoc' => $h->is_adhoc,
                    ]);
                }
                foreach ($source->patterns as $p) {
                    SeasonPattern::create(array_merge($p->only([
                        'day_of_week', 'start_time', 'end_time', 'event_type', 'title',
                        'location', 'description', 'max_participants', 'estimated_cost',
                        'registration_opens_days_before', 'registration_closes_days_before',
                        'color_hex', 'whatsapp_group_url', 'dive_site_id',
                    ]), ['season_id' => $season->id]));
                }
            }
        }

        return redirect()->route('admin.seasons.show', $season)->with('success', __('Season created.'));
    }

    public function show(Season $season): JsonResponse|RedirectResponse|View
    {
        $season->load(['holidays', 'patterns']);

        return view('admin.seasons.show', [
            'season' => $season,
            'activityTypes' => config('activity_types'),
        ]);
    }

    public function activate(Season $season): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($season): void {
            Season::where('is_active', true)->update(['is_active' => false]);
            $season->update(['is_active' => true]);
        });

        return back()->with('success', __('Season activated.'));
    }

    /**
     * Update the season's fee-taper schedule (ordered MM-DD → percentage tiers).
     */
    public function updateTaper(Request $request, Season $season): RedirectResponse
    {
        abort_unless(auth()->user()?->can('manage seasons'), 403);

        $validated = $request->validate([
            'from' => 'array',
            'from.*' => ['required', 'regex:/^\d{2}-\d{2}$/'],
            'pct' => 'array',
            'pct.*' => 'required|integer|min:0|max:100',
        ]);

        $froms = $validated['from'] ?? [];
        $pcts = $validated['pct'] ?? [];

        $tiers = [];
        foreach ($froms as $i => $from) {
            if (! isset($pcts[$i])) {
                continue;
            }
            $tiers[] = ['from' => $from, 'pct' => (int) $pcts[$i]];
        }

        // Sort by month-day for a stable, readable schedule.
        usort($tiers, fn (array $a, array $b): int => strcmp($a['from'], $b['from']));

        $season->update(['fee_taper_tiers' => $tiers === [] ? null : $tiers]);

        return back()->with('success', __('Fee taper schedule updated.'));
    }

    // Holiday management
    public function storeHoliday(StoreSeasonHolidayRequest $request, Season $season): JsonResponse|RedirectResponse|View
    {
        $v = $request->validated();
        $v['is_adhoc'] = $request->boolean('is_adhoc');
        $holiday = $season->holidays()->create($v);

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $holiday->id,
                'name' => $holiday->name,
                'start_date' => $holiday->start_date->format('d/m'),
                'end_date' => $holiday->end_date->format('d/m/Y'),
                'is_adhoc' => $holiday->is_adhoc,
                'delete_url' => route('admin.seasons.holiday.destroy', $holiday),
            ]);
        }

        return back()->with('success', __('Holiday added.'));
    }

    public function destroyHoliday(SeasonHoliday $holiday): JsonResponse|RedirectResponse|View
    {
        $holiday->delete();

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Holiday removed.'));
    }

    // Pattern management
    public function storePattern(StoreSeasonPatternRequest $request, Season $season): JsonResponse|RedirectResponse|View
    {
        $v = $request->validated();
        $pattern = $season->patterns()->create($v);

        if ($request->wantsJson()) {
            return response()->json(array_merge($pattern->toArray(), [
                'delete_url' => route('admin.seasons.pattern.destroy', $pattern),
            ]));
        }

        return back()->with('success', __('Pattern added.'));
    }

    public function destroyPattern(SeasonPattern $pattern): JsonResponse|RedirectResponse|View
    {
        $pattern->delete();

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Pattern removed.'));
    }

    public function updatePattern(Request $request, SeasonPattern $pattern): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time' => 'required|string|max:5',
            'end_time' => 'nullable|string|max:5',
            'event_type' => 'required|in:'.implode(',', array_keys(config('activity_types', []))),
            'title' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'max_participants' => 'nullable|integer|min:1',
            'estimated_cost' => 'nullable|numeric|min:0',
            'registration_opens_days_before' => 'nullable|integer|min:1',
            'registration_closes_days_before' => 'nullable|integer|min:0',
            'color_hex' => 'nullable|string|max:7',
            'whatsapp_group_url' => 'nullable|url|max:500',
            'propagate' => 'boolean',
        ]);

        $propagate = $request->boolean('propagate');
        $patternFields = collect($validated)->except('propagate')->toArray();

        $pattern->update($patternFields);

        // Propagate changes to future events in this season matching this pattern's day
        if ($propagate && $pattern->season_id) {
            $carbonDay = ((int) $pattern->day_of_week + 1) % 7;
            $propagatableFields = collect($patternFields)->only([
                'event_type', 'title', 'location', 'description', 'max_participants',
                'estimated_cost', 'color_hex', 'whatsapp_group_url',
            ])->filter(fn ($v) => $v !== null || in_array($v, ['description', 'whatsapp_group_url']))->toArray();

            // Map pattern fields to event fields
            $eventUpdates = [];
            foreach ($propagatableFields as $key => $value) {
                $eventUpdates[$key] = $value;
            }

            // Also propagate time changes
            if (isset($patternFields['start_time'])) {
                $eventUpdates['event_time'] = $patternFields['start_time'];
            }
            if (array_key_exists('end_time', $patternFields)) {
                $eventUpdates['end_time'] = $patternFields['end_time'];
            }

            // Prefer the precise pattern link; fall back to a weekday match for
            // legacy events created before season_pattern_id existed. The
            // weekday fallback uses Carbon (cross-DB) rather than a DB-specific
            // date function so it works on both MySQL and PostgreSQL.
            $futureEvents = Event::where('season_id', $pattern->season_id)
                ->where('event_date', '>=', now()->toDateString())
                ->where(function ($q) use ($pattern): void {
                    $q->where('season_pattern_id', $pattern->id)
                        ->orWhereNull('season_pattern_id');
                })
                ->get();

            $updated = 0;
            foreach ($futureEvents as $ev) {
                $matches = $ev->season_pattern_id === $pattern->id
                    || ($ev->season_pattern_id === null && $ev->event_date->dayOfWeek === $carbonDay);
                if (! $matches) {
                    continue;
                }
                $ev->update(array_merge($eventUpdates, ['season_pattern_id' => $pattern->id]));
                $updated++;
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'pattern' => $pattern->fresh(),
                'events_updated' => $propagate ? ($updated ?? 0) : 0,
            ]);
        }

        $msg = __('Pattern updated.');
        if ($propagate && isset($updated) && $updated > 0) {
            $msg .= ' '.__(':count future events updated.', ['count' => $updated]);
        }

        return back()->with('success', $msg);
    }

    // Generate events preview
    public function previewGeneration(Season $season): RedirectResponse|View
    {
        $season->load(['patterns', 'holidays']);
        $preview = $this->buildSchedule($season);

        return view('admin.seasons.preview', compact('season', 'preview'));
    }

    // Confirm generation
    public function generateEvents(Season $season): RedirectResponse
    {
        $season->load(['patterns', 'holidays']);
        $schedule = $this->buildSchedule($season);
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($schedule, $season, &$created, &$updated, &$skipped): void {
            foreach ($schedule as $entry) {
                if ($entry['skip']) {
                    continue;
                }
                $pattern = $entry['pattern'];

                // Positional identity: one occurrence per (pattern, date).
                // Re-running generation updates the existing occurrence in place
                // rather than creating a duplicate. The match must depend only on
                // WHEN the event happens (pattern link, or season+date+time), never
                // on mutable details like event_type/title — otherwise editing a
                // pattern's type would orphan the old events and create duplicates.
                $existing = Event::where('season_pattern_id', $pattern->id)
                    ->whereDate('event_date', $entry['date'])
                    ->first();

                if (! $existing) {
                    $existing = Event::whereNull('season_pattern_id')
                        ->where('season_id', $season->id)
                        ->whereDate('event_date', $entry['date'])
                        ->where('event_time', $pattern->start_time)
                        ->first();
                }

                // Descriptive fields — always synced from the pattern.
                $details = [
                    'title' => $pattern->title,
                    'color_hex' => $pattern->color_hex,
                    'event_type' => $pattern->event_type,
                    'event_time' => $pattern->start_time,
                    'end_time' => $pattern->end_time,
                    'location' => $pattern->location,
                    'description' => $pattern->description,
                    'max_participants' => $pattern->max_participants,
                    'estimated_cost' => $pattern->estimated_cost,
                    'whatsapp_group_url' => $pattern->whatsapp_group_url,
                    'dive_site_id' => $pattern->dive_site_id,
                ];

                if ($existing) {
                    // A cancelled occurrence stays cancelled: skip it entirely so
                    // re-generation never reopens it or overwrites its details.
                    if ($existing->status === 'cancelled') {
                        // Ensure it carries the pattern link so future runs match
                        // it by the strong key and keep skipping it.
                        if ($existing->season_pattern_id !== $pattern->id) {
                            $existing->update([
                                'season_pattern_id' => $pattern->id,
                                'season_id' => $season->id,
                            ]);
                        }
                        $skipped++;

                        continue;
                    }

                    // Only alter details. Leave registration state untouched
                    // (inscriptions_closed, inscription_open_at, participants,
                    // status) so fixing a schedule never reopens or resets an
                    // event that members already interacted with.
                    $existing->update(array_merge($details, [
                        'season_pattern_id' => $pattern->id,
                        'season_id' => $season->id,
                    ]));
                    $updated++;

                    continue;
                }

                Event::create(array_merge($details, [
                    'event_date' => $entry['date'],
                    'waiting_list_enabled' => true,
                    'inscription_open_at' => $pattern->registration_opens_days_before
                        ? $entry['date']->copy()->subDays($pattern->registration_opens_days_before)->startOfDay()
                        : null,
                    'inscriptions_closed' => false,
                    'status' => 'scheduled',
                    'season_id' => $season->id,
                    'season_pattern_id' => $pattern->id,
                    'created_by' => auth()->id(),
                ]));
                $created++;
            }
        });

        $message = __(':count events generated.', ['count' => $created]);
        if ($updated > 0) {
            $message .= ' '.__(':count existing events updated.', ['count' => $updated]);
        }
        if ($skipped > 0) {
            $message .= ' '.__(':count cancelled occurrences left untouched.', ['count' => $skipped]);
        }

        return redirect()->route('admin.seasons.show', $season)->with('success', $message);
    }

    private function buildSchedule(Season $season): array
    {
        $schedule = [];
        $holidays = $season->holidays;

        foreach ($season->patterns as $pattern) {
            // Carbon day_of_week: 0=Sunday..6=Saturday, but we store 0=Monday..6=Sunday
            $carbonDay = ($pattern->day_of_week + 1) % 7;
            $current = $season->start_date->copy();

            // Find first matching day
            while ($current->dayOfWeek !== $carbonDay && $current->lte($season->end_date)) {
                $current->addDay();
            }

            while ($current->lte($season->end_date)) {
                $skip = false;
                $skipReason = null;

                foreach ($holidays as $h) {
                    if ($current->between($h->start_date, $h->end_date)) {
                        $skip = true;
                        $skipReason = $h->name.($h->is_adhoc ? ' (ad-hoc)' : '');
                        break;
                    }
                }

                $schedule[] = [
                    'date' => $current->copy(),
                    'pattern' => $pattern,
                    'skip' => $skip,
                    'skip_reason' => $skipReason,
                ];

                $current->addWeek();
            }
        }

        usort($schedule, fn (array $a, array $b): int|float => $a['date']->timestamp - $b['date']->timestamp);

        return $schedule;
    }
}
