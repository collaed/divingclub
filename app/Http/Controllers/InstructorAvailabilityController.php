<?php

namespace App\Http\Controllers;

use App\Models\InstructorAvailability;
use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class InstructorAvailabilityController extends Controller
{
    /**
     * Activity types with colors matching the old CEP Google Sheet planning.
     */
    public const ACTIVITY_COLORS = [
        'pool'       => ['color' => '#c9daf8', 'text' => '#000', 'icon' => '🏊', 'label' => 'Pool'],
        'pool_kids'  => ['color' => '#6d9eeb', 'text' => '#fff', 'icon' => '👶', 'label' => 'Kids'],
        'pool_pn1'   => ['color' => '#1155cc', 'text' => '#fff', 'icon' => '1️⃣', 'label' => 'PN1'],
        'pool_pn23'  => ['color' => '#c9daf8', 'text' => '#f00', 'icon' => '🔴', 'label' => 'PN2-3'],
        'apnea'      => ['color' => '#00ff00', 'text' => '#000', 'icon' => '🫁', 'label' => 'Apnea'],
        'fosse'      => ['color' => '#93c47d', 'text' => '#000', 'icon' => '🕳️', 'label' => 'Fosse'],
        'quarry'     => ['color' => '#ff00ff', 'text' => '#000', 'icon' => '🪨', 'label' => 'Quarry/Lake'],
        'long_trip'  => ['color' => '#ffe599', 'text' => '#000', 'icon' => '✈️', 'label' => 'Long Trip'],
        'theory'     => ['color' => '#d9d9d9', 'text' => '#000', 'icon' => '📖', 'label' => 'Theory'],
        'steinfort'  => ['color' => '#ff9900', 'text' => '#000', 'icon' => '🟠', 'label' => 'Steinfort'],
    ];

    public function index(Request $request)
    {
        $user = auth()->user();
        $isInstructor = $user->hasAnyRole(['instructor', 'bureau_master', 'bureau_technical', 'assistant']);

        $month = $request->query('month', now()->format('Y-m'));
        $start = \Carbon\Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $availabilities = InstructorAvailability::with('user.detail')
            ->whereBetween('date', [$start, $end])
            ->get()
            ->groupBy(fn($a) => $a->date->format('Y-m-d'));

        // Events this month for context
        $events = Event::whereBetween('event_date', [$start, $end])
            ->orderBy('event_date')
            ->get()
            ->groupBy(fn($e) => $e->event_date->format('Y-m-d'));

        $instructorRoleIds = Role::whereIn('slug', ['instructor', 'bureau_master', 'bureau_technical', 'assistant'])->pluck('id');
        $instructors = User::whereIn('role_id', $instructorRoleIds)->with('detail')->get()
            ->sortBy(fn($u) => $u->detail?->first_name);

        $colors = self::ACTIVITY_COLORS;

        return view('availability.index', compact('availabilities', 'events', 'start', 'end', 'isInstructor', 'instructors', 'month', 'colors'));
    }

    public function toggle(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['instructor', 'bureau_master', 'bureau_technical', 'assistant'])) {
            abort(403);
        }

        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'slot' => 'required|in:morning,afternoon,evening,full_day',
            'activity_type' => 'required|in:' . implode(',', array_keys(self::ACTIVITY_COLORS)),
        ]);

        $existing = InstructorAvailability::where('user_id', $user->id)
            ->where('date', $request->date)
            ->where('slot', $request->slot)
            ->where('activity_type', $request->activity_type)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['status' => 'removed']);
        }

        InstructorAvailability::create([
            'user_id' => $user->id,
            'date' => $request->date,
            'slot' => $request->slot,
            'activity_type' => $request->activity_type,
        ]);

        return response()->json(['status' => 'added']);
    }
}
