<?php

namespace App\Http\Controllers;

use App\Models\InstructorAvailability;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class InstructorAvailabilityController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isInstructor = $user->hasAnyRole(['instructor', 'bureau_master', 'bureau_technical', 'assistant']);

        // Month navigation: default current, allow ?month=2026-04
        $month = $request->query('month', now()->format('Y-m'));
        $start = \Carbon\Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // All availabilities for the month
        $availabilities = InstructorAvailability::with('user.detail')
            ->whereBetween('date', [$start, $end])
            ->get()
            ->groupBy(fn($a) => $a->date->format('Y-m-d'));

        // Instructor list for the legend
        $instructorRoleIds = Role::whereIn('slug', ['instructor', 'bureau_master', 'bureau_technical', 'assistant'])->pluck('id');
        $instructors = User::whereIn('role_id', $instructorRoleIds)->with('detail')->get();

        return view('availability.index', compact('availabilities', 'start', 'end', 'isInstructor', 'instructors', 'month'));
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
        ]);

        $existing = InstructorAvailability::where('user_id', $user->id)
            ->where('date', $request->date)
            ->where('slot', $request->slot)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['status' => 'removed']);
        }

        InstructorAvailability::create([
            'user_id' => $user->id,
            'date' => $request->date,
            'slot' => $request->slot,
        ]);

        return response()->json(['status' => 'added']);
    }
}
