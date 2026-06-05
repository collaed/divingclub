<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AnnualReportController extends Controller
{
    public function show(Request $request): View
    {
        $year = (int) $request->get('year', date('Y'));
        $years = range(date('Y'), (int) (User::min('created_at') ? substr(User::min('created_at'), 0, 4) : date('Y') - 3), -1);

        // Members over time (last 5 years)
        $membersTrend = collect(range($year - 4, $year))->map(fn ($y): array => [
            'year' => $y,
            'count' => User::where('created_at', '<=', "$y-12-31")->count(),
        ]);

        // Events by type this year
        $eventsByType = Event::whereYear('event_date', $year)
            ->selectRaw('event_type, count(*) as cnt')
            ->groupBy('event_type')->pluck('cnt', 'event_type');

        // Monthly participation (confirmed registrations per month)
        $monthlyParticipation = collect(range(1, 12))->map(function ($m) use ($year): array {
            return [
                'month' => $m,
                'label' => date('M', mktime(0, 0, 0, $m)),
                'count' => EventRegistration::where('status', 'confirmed')
                    ->whereHas('event', fn ($q) => $q->whereYear('event_date', $year)->whereMonth('event_date', $m))
                    ->count(),
            ];
        });

        // Social vs diving events participation
        $socialVsDiving = [
            'diving' => Event::whereYear('event_date', $year)->whereIn('event_type', ['pool', 'dive', 'training'])->withCount('confirmedRegistrations')->get()->sum('confirmed_registrations_count'),
            'social' => Event::whereYear('event_date', $year)->where('event_type', 'social')->withCount('confirmedRegistrations')->get()->sum('confirmed_registrations_count'),
            'theory' => Event::whereYear('event_date', $year)->where('event_type', 'theory')->withCount('confirmedRegistrations')->get()->sum('confirmed_registrations_count'),
        ];

        // Financial summary
        $finance = [
            'revenue' => PaymentExpected::where('season_year', $year)->where('status', 'paid')->sum('amount_paid'),
            'outstanding' => PaymentExpected::where('season_year', $year)->where('status', 'pending')->sum('amount_due'),
            'total_due' => PaymentExpected::where('season_year', $year)->sum('amount_due'),
            'paid_count' => PaymentExpected::where('season_year', $year)->where('status', 'paid')->count(),
            'pending_count' => PaymentExpected::where('season_year', $year)->where('status', 'pending')->count(),
        ];

        // Members by status
        $membersByStatus = MemberStatus::withCount('users')->get();

        // New members this year
        $newMembers = User::whereYear('created_at', $year)->count();

        // Total events
        $totalEvents = Event::whereYear('event_date', $year)->where('status', '!=', 'cancelled')->count();

        // Before/after comparisons
        $startOfYear = "$year-01-01";
        $endOfYear = "$year-12-31";
        $beforeAfter = [
            'members_start' => User::where('created_at', '<', $startOfYear)->count(),
            'members_end' => User::where('created_at', '<=', $endOfYear)->count(),
            'departed' => 0, // TODO: track departures when member status tracking is added
            'revenue_start' => PaymentExpected::where('season_year', $year - 1)->where('status', 'paid')->sum('amount_paid'),
            'revenue_end' => $finance['revenue'],
            'main_events' => Event::whereYear('event_date', $year)
                ->where('status', '!=', 'cancelled')
                ->whereIn('event_type', ['dive', 'trip', 'social'])
                ->withCount('confirmedRegistrations')
                ->orderByDesc('confirmed_registrations_count')
                ->limit(10)->get(),
        ];

        return view('admin.annual-report', compact(
            'year', 'years', 'membersTrend', 'eventsByType', 'monthlyParticipation',
            'socialVsDiving', 'finance', 'membersByStatus', 'newMembers', 'totalEvents', 'beforeAfter'
        ));
    }
}
