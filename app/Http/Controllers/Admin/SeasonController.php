<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Season;
use App\Models\SeasonHoliday;
use App\Models\SeasonPattern;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeasonController extends Controller
{
    public function index()
    {
        $seasons = Season::withCount('events')->orderByDesc('year')->get();

        return view('admin.seasons.index', compact('seasons'));
    }

    public function create()
    {
        $previousSeasons = Season::orderByDesc('year')->get();

        return view('admin.seasons.form', ['season' => new Season, 'previousSeasons' => $previousSeasons]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'year' => 'required|integer|min:2000',
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

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
                    SeasonPattern::create(array_merge($p->only(['day_of_week', 'start_time', 'end_time', 'event_type', 'title', 'location', 'max_participants', 'color_hex']), ['season_id' => $season->id]));
                }
            }
        }

        return redirect()->route('admin.seasons.show', $season)->with('success', __('Season created.'));
    }

    public function show(Season $season)
    {
        $season->load(['holidays', 'patterns']);

        return view('admin.seasons.show', compact('season'));
    }

    public function activate(Season $season)
    {
        DB::transaction(function () use ($season) {
            Season::where('is_active', true)->update(['is_active' => false]);
            $season->update(['is_active' => true]);
        });

        return back()->with('success', __('Season activated.'));
    }

    // Holiday management
    public function storeHoliday(Request $request, Season $season)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_adhoc' => 'boolean',
        ]);
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

    public function destroyHoliday(SeasonHoliday $holiday)
    {
        $holiday->delete();

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Holiday removed.'));
    }

    // Pattern management
    public function storePattern(Request $request, Season $season)
    {
        $v = $request->validate([
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'event_type' => 'required|in:pool,dive,training,theory,social',
            'title' => 'required|string|max:255',
            'location' => 'nullable|string|max:500',
            'max_participants' => 'nullable|integer|min:1',
            'registration_opens_days_before' => 'nullable|integer|min:1',
            'color_hex' => 'nullable|string|max:7',
        ]);
        $pattern = $season->patterns()->create($v);

        if ($request->wantsJson()) {
            return response()->json(array_merge($pattern->toArray(), [
                'delete_url' => route('admin.seasons.pattern.destroy', $pattern),
            ]));
        }

        return back()->with('success', __('Pattern added.'));
    }

    public function destroyPattern(SeasonPattern $pattern)
    {
        $pattern->delete();

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Pattern removed.'));
    }

    // Generate events preview
    public function previewGeneration(Season $season)
    {
        $season->load(['patterns', 'holidays']);
        $preview = $this->buildSchedule($season);

        return view('admin.seasons.preview', compact('season', 'preview'));
    }

    // Confirm generation
    public function generateEvents(Season $season)
    {
        $season->load(['patterns', 'holidays']);
        $schedule = $this->buildSchedule($season);
        $created = 0;

        DB::transaction(function () use ($schedule, $season, &$created) {
            foreach ($schedule as $entry) {
                if ($entry['skip']) {
                    continue;
                }
                $pattern = $entry['pattern'];
                Event::create([
                    'title' => $pattern->title,
                    'color_hex' => $pattern->color_hex,
                    'event_type' => $pattern->event_type,
                    'event_date' => $entry['date'],
                    'event_time' => $pattern->start_time,
                    'end_time' => $pattern->end_time,
                    'location' => $pattern->location,
                    'max_participants' => $pattern->max_participants,
                    'waiting_list_enabled' => true,
                    'inscription_open_at' => $pattern->registration_opens_days_before
                        ? $entry['date']->copy()->subDays($pattern->registration_opens_days_before)->startOfDay()
                        : null,
                    'status' => 'scheduled',
                    'season_id' => $season->id,
                    'created_by' => auth()->id(),
                    'whatsapp_group_url' => $pattern->whatsapp_group_url,
                ]);
                $created++;
            }
        });

        return redirect()->route('admin.seasons.show', $season)->with('success', __(':count events generated.', ['count' => $created]));
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

        usort($schedule, fn ($a, $b) => $a['date']->timestamp - $b['date']->timestamp);

        return $schedule;
    }
}
