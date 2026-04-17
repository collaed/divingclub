<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\InstructorAvailability;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstructorAvailabilityController extends Controller
{
    /**
     * Activity types with colors matching the old CEP Google Sheet planning.
     */
    public const ACTIVITY_COLORS = [
        'pool' => ['color' => '#4a86c8', 'text' => '#fff', 'icon' => '🏊', 'label' => 'Pool'],
        'pool_kids' => ['color' => '#2ecc71', 'text' => '#fff', 'icon' => '👶', 'label' => '↳ Kids'],
        'pool_pn1' => ['color' => '#1a237e', 'text' => '#fff', 'icon' => '1️⃣', 'label' => '↳ PN1'],
        'pool_pn23' => ['color' => '#e74c3c', 'text' => '#fff', 'icon' => '🔴', 'label' => '↳ PN2+'],
        'pool_swimming' => ['color' => '#ff9800', 'text' => '#fff', 'icon' => '🏊‍♂️', 'label' => '↳ Swimming'],
        'apnea' => ['color' => '#00c853', 'text' => '#000', 'icon' => '🫁', 'label' => 'Apnea'],
        'fosse' => ['color' => '#00695c', 'text' => '#fff', 'icon' => '🕳️', 'label' => 'Fosse'],
        'quarry' => ['color' => '#00bcd4', 'text' => '#000', 'icon' => '🪨', 'label' => 'Quarry/Lake'],
        'long_trip' => ['color' => '#f9a825', 'text' => '#000', 'icon' => '✈️', 'label' => 'Long Trip'],
        'theory' => ['color' => '#78909c', 'text' => '#fff', 'icon' => '📖', 'label' => 'Theory'],
        'social' => ['color' => '#e91e63', 'text' => '#fff', 'icon' => '🎉', 'label' => 'Social'],
    ];

    public function index(Request $request)
    {
        $user = auth()->user();
        $isInstructor = $user->hasAnyRole(['instructor', 'instructor_apnea', 'bureau_master', 'bureau_technical', 'assistant']);

        $month = $request->query('month', now()->format('Y-m'));
        try {
            $start = Carbon::parse($month.'-01')->startOfMonth();
        } catch (\Throwable) {
            $start = now()->startOfMonth();
            $month = $start->format('Y-m');
        }
        $end = $start->copy()->endOfMonth();

        $availabilities = InstructorAvailability::with('user.detail')
            ->whereBetween('date', [$start, $end])
            ->get()
            ->groupBy(fn ($a) => $a->date->format('Y-m-d'));

        $events = Event::whereBetween('event_date', [$start, $end])
            ->orderBy('event_date')
            ->get()
            ->groupBy(fn ($e) => $e->event_date->format('Y-m-d'));

        $instructors = User::role(['instructor', 'instructor_apnea', 'bureau_master', 'bureau_technical'])->with('detail')->get()
            ->sortBy(fn ($u) => $u->detail?->first_name);

        $colors = self::ACTIVITY_COLORS;

        return view('availability.index', compact('availabilities', 'events', 'start', 'end', 'isInstructor', 'instructors', 'month', 'colors'));
    }

    public function toggle(Request $request)
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(['instructor', 'instructor_apnea', 'bureau_master', 'bureau_technical', 'assistant'])) {
            abort(403);
        }

        $request->validate([
            'event_id' => 'required|exists:events,id',
        ]);

        $event = Event::findOrFail($request->event_id);

        if ($event->event_date->lt(today())) {
            return response()->json(['status' => 'error', 'message' => 'Past event'], 422);
        }

        $existing = InstructorAvailability::where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->first();

        if ($existing) {
            $existing->delete();

            // Also cancel registration if they had one
            EventRegistration::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->whereIn('status', ['confirmed', 'waiting'])
                ->update(['status' => 'cancelled']);

            return response()->json(['status' => 'removed']);
        }

        DB::transaction(function () use ($user, $event) {
            InstructorAvailability::create([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'date' => $event->event_date,
                'slot' => 'evening',
                'activity_type' => $event->event_type ?? 'pool',
            ]);

            // Auto-register if event accepts registrations and not already registered
            if ($event->isRegistrationOpen()) {
                $alreadyRegistered = $event->registrations()
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['confirmed', 'waiting'])
                    ->exists();

                if (! $alreadyRegistered) {
                    // Remove old cancelled registration
                    $event->registrations()->where('user_id', $user->id)->where('status', 'cancelled')->delete();

                    EventRegistration::create([
                        'event_id' => $event->id,
                        'user_id' => $user->id,
                        'status' => $event->isFull() && $event->waiting_list_enabled ? 'waiting' : 'confirmed',
                        'comment' => 'Instructor availability',
                    ]);
                }
            }
        });

        return response()->json(['status' => 'added']);
    }
}
