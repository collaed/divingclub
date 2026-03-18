<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Season;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\MedicalComplianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->get('view', 'month');
        $date = $request->get('date') ? \Carbon\Carbon::parse($request->get('date')) : now();

        $query = Event::where('status', '!=', 'cancelled');

        if ($view === 'month') {
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();
        } elseif ($view === 'week') {
            $start = $date->copy()->startOfWeek();
            $end = $date->copy()->endOfWeek();
        } else {
            $start = $date->copy()->startOfDay();
            $end = $date->copy()->endOfDay();
        }

        $events = $query->whereBetween('event_date', [$start, $end])
            ->orderBy('event_date')->orderBy('event_time')
            ->withCount(['confirmedRegistrations as confirmed_count', 'waitingRegistrations as waiting_count'])
            ->get();

        return view('events.index', compact('events', 'view', 'date', 'start', 'end'));
    }

    public function show(Event $event)
    {
        $event->load(['registrations.user.detail', 'registrations.user.certificationLevels', 'instructor.detail', 'responsible.detail', 'season', 'diveSite', 'diveGroups.members.user.certificationLevels']);
        $userReg = auth()->check() ? $event->registrations()->where('user_id', auth()->id())->first() : null;

        return view('events.show', compact('event', 'userReg'));
    }

    public function create()
    {
        $this->authorizeBureau();
        $seasons = Season::orderByDesc('year')->get();
        $instructors = User::whereHas('role', fn($q) => $q->whereIn('slug', ['instructor', 'bureau_master']))->with('detail')->get();
        $diveSites = \App\Models\DiveSite::active()->orderBy('name')->get();
        $locationSuggestions = $this->topLocations();
        return view('events.form', ['event' => new Event, 'seasons' => $seasons, 'instructors' => $instructors, 'diveSites' => $diveSites, 'locationSuggestions' => $locationSuggestions]);
    }

    public function store(Request $request)
    {
        $this->authorizeBureau();
        $data = $this->validateEvent($request);
        $data['created_by'] = auth()->id();
        $data['assistant_ids'] = $request->assistant_ids ? array_map('intval', explode(',', $request->assistant_ids)) : [];
        $data['participant_email'] = null; // will be set after creation

        $event = Event::create($data);
        $event->update(['participant_email' => 'event-' . $event->id . '@' . config('club.domain')]);

        return redirect()->route('events.show', $event)->with('success', __('Event created.'));
    }

    public function edit(Event $event)
    {
        $this->authorizeEventEdit($event);
        $seasons = Season::orderByDesc('year')->get();
        $instructors = User::whereHas('role', fn($q) => $q->whereIn('slug', ['instructor', 'bureau_master']))->with('detail')->get();
        $diveSites = \App\Models\DiveSite::active()->orderBy('name')->get();
        $locationSuggestions = $this->topLocations();
        return view('events.form', compact('event', 'seasons', 'instructors', 'diveSites', 'locationSuggestions'));
    }

    public function update(Request $request, Event $event)
    {
        $this->authorizeEventEdit($event);
        $data = $this->validateEvent($request);
        $data['assistant_ids'] = $request->assistant_ids ? array_map('intval', explode(',', $request->assistant_ids)) : [];
        $event->update($data);
        return redirect()->route('events.show', $event)->with('success', __('Event updated.'));
    }

    public function register(Event $event)
    {
        $user = auth()->user();

        if (!$event->isRegistrationOpen()) {
            return back()->with('error', __('Registration is not open for this event.'));
        }

        if ($event->registrations()->where('user_id', $user->id)->whereIn('status', ['confirmed', 'waiting'])->exists()) {
            return back()->with('error', __('You are already registered.'));
        }

        // Remove old cancelled registration if re-registering
        $event->registrations()->where('user_id', $user->id)->where('status', 'cancelled')->delete();

        // Medical compliance gate — pool, dive, training require valid cert
        if (in_array($event->event_type, ['pool', 'dive', 'training'])) {
            if (!app(MedicalComplianceService::class)->isCompliant($user)) {
                return back()->with('error', __('You need a valid medical certificate to register for this event. Please upload one in your profile.'));
            }
        }

        DB::transaction(function () use ($event, $user) {
            if ($event->isFull()) {
                if (!$event->waiting_list_enabled) {
                    return back()->with('error', __('Event is full.'));
                }
                $pos = ($event->waitingRegistrations()->max('waiting_list_position') ?? 0) + 1;
                EventRegistration::create([
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'status' => 'waiting',
                    'waiting_list_position' => $pos,
                ]);
            } else {
                EventRegistration::create([
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'status' => 'confirmed',
                ]);

                // Auto-generate payment only for deposits (not estimated_cost)
                $totalDue = 0;
                $components = [];
                foreach ([1, 2, 3] as $i) {
                    $amt = $event->{"deposit_{$i}_amount"};
                    if ($amt > 0) {
                        $totalDue += $amt;
                        $components[] = ['label' => __('Deposit') . " $i" . ($event->{"deposit_{$i}_date"} ? ' (' . $event->{"deposit_{$i}_date"}->format('d/m/Y') . ')' : ''), 'amount' => (float) $amt];
                    }
                }
                if ($totalDue > 0) {
                    $detail = $user->detail;
                    $name = strtoupper($detail?->last_name ?? 'MEMBER');
                    \App\Models\PaymentExpected::create([
                        'user_id' => $user->id,
                        'type' => 'event',
                        'event_id' => $event->id,
                        'season_year' => $event->event_date->format('Y'),
                        'amount_due' => $totalDue,
                        'communication' => ThemeSetting::get('club_short_code', config('club.id', 'CLUB')) . '-' . $event->event_date->format('Y') . '-' . $event->id . '-' . $name,
                        'components' => $components,
                        'status' => 'pending',
                    ]);
                }
            }
        });

        return back()->with('success', __('Registration successful.'));
    }

    public function cancelRegistration(Event $event)
    {
        $reg = $event->registrations()->where('user_id', auth()->id())->firstOrFail();
        $wasConfirmed = $reg->status === 'confirmed';

        $reg->update(['status' => 'cancelled']);

        // Cancel unpaid payment
        \App\Models\PaymentExpected::where('event_id', $event->id)
            ->where('user_id', auth()->id())
            ->where('status', 'pending')
            ->delete();

        // Auto-promote first waiting list entry
        if ($wasConfirmed) {
            $next = $event->waitingRegistrations()->first();
            if ($next) {
                $next->update(['status' => 'confirmed', 'waiting_list_position' => null]);
                // TODO: Send promotion notification email (Phase 6)
            }
        }

        return back()->with('success', __('Registration cancelled.'));
    }

    public function cancel(Event $event)
    {
        $this->authorizeBureau();
        $event->update(['status' => 'cancelled']);
        return redirect()->route('events.index')->with('success', __('Event cancelled.'));
    }

    public function uploadPhoto(Request $request, Event $event)
    {
        $request->validate(['photos.*' => 'required|image|max:10240', 'caption' => 'nullable|string|max:255']);

        // GDPR: check photo_publication consent
        $consent = \App\Models\GdprConsent::where('user_id', auth()->id())
            ->where('consent_type', 'photo_publication')->where('granted', true)->exists();
        if (!$consent) {
            return back()->with('error', __('You must grant photo publication consent in Privacy settings before uploading event photos.'));
        }

        foreach ($request->file('photos', []) as $file) {
            $path = $file->store('event-photos/' . $event->id, 'public');

            // Auto quality score based on file size + dimensions (simple heuristic)
            $size = $file->getSize();
            $img = @getimagesize($file->getRealPath());
            $megapixels = $img ? ($img[0] * $img[1]) / 1_000_000 : 0;
            $score = min(100, (int)(
                ($megapixels >= 2 ? 40 : $megapixels * 20) +
                ($size > 500_000 ? 30 : ($size / 500_000) * 30) +
                ($img && $img[0] > $img[1] ? 20 : 10) + // landscape bonus
                ($img && $img[0] >= 1920 ? 10 : 0) // HD bonus
            ));

            \App\Models\EventPhoto::create([
                'event_id' => $event->id,
                'uploaded_by' => auth()->id(),
                'path' => $path,
                'caption' => $request->caption,
                'quality_score' => $score,
            ]);
        }

        return back()->with('success', __('Photos uploaded.'));
    }

    public function deletePhoto(Event $event, \App\Models\EventPhoto $photo)
    {
        $user = auth()->user();
        abort_unless($photo->event_id === $event->id, 404);
        abort_unless($user->isBureau() || $photo->uploaded_by === $user->id, 403);

        \Illuminate\Support\Facades\Storage::disk('public')->delete($photo->path);
        $photo->delete();
        return back()->with('success', __('Photo deleted.'));
    }

    private function validateEvent(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'color_hex' => 'nullable|string|max:7',
            'event_type' => 'required|in:pool,dive,training,theory,social',
            'event_date' => 'required|date',
            'event_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'end_date' => 'nullable|date|after_or_equal:event_date',
            'location' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'responsible_id' => 'nullable|exists:users,id',
            'max_participants' => 'nullable|integer|min:1',
            'waiting_list_enabled' => 'boolean',
            'inscription_open_at' => 'nullable|date',
            'inscriptions_closed' => 'boolean',
            'levels_display' => 'boolean',
            'confirmation_required' => 'boolean',
            'estimated_cost' => 'nullable|numeric|min:0',
            'deposit_1_date' => 'nullable|date',
            'deposit_1_amount' => 'nullable|numeric|min:0',
            'deposit_2_date' => 'nullable|date',
            'deposit_2_amount' => 'nullable|numeric|min:0',
            'deposit_3_date' => 'nullable|date',
            'deposit_3_amount' => 'nullable|numeric|min:0',
            'instructor_id' => 'nullable|exists:users,id',
            'permissions_expire_date' => 'nullable|date',
            'status' => 'nullable|in:scheduled,cancelled,completed',
            'season_id' => 'nullable|exists:seasons,id',
            'dive_site_id' => 'nullable|exists:dive_sites,id',
        ]);
    }

    private function authorizeBureau(): void
    {
        abort_unless(auth()->user()->isBureau(), 403);
    }

    private function authorizeEventEdit(Event $event): void
    {
        $user = auth()->user();
        if ($user->isBureau()) return;
        if ($event->instructor_id === $user->id && (!$event->permissions_expire_date || $event->permissions_expire_date->isFuture())) return;
        abort(403);
    }

    private function topLocations(): array
    {
        return Event::selectRaw('location, count(*) as cnt')
            ->whereNotNull('location')->where('location', '!=', '')
            ->groupBy('location')->orderByDesc('cnt')
            ->pluck('location')->all();
    }
}
