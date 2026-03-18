<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Event;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $season = $request->get('season', date('Y'));

        $stats = [
            'total_members' => User::count(),
            'members_by_status' => MemberStatus::withCount('users')->get()->map(fn($s) => ['name' => $s->name, 'count' => $s->users_count]),
            'new_members_this_year' => User::whereYear('created_at', $season)->count(),
            'events_count' => Event::whereYear('event_date', $season)->count(),
            'avg_attendance' => round(Event::whereYear('event_date', $season)->withCount('confirmedRegistrations')->get()->avg('confirmed_registrations_count') ?? 0, 1),
            'equipment_by_status' => Equipment::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status'),
            'certs_expiring_30d' => Document::where('category', 'medical')->where('is_current', true)->whereBetween('expiry_date', [now(), now()->addDays(30)])->count(),
            'revenue' => PaymentExpected::where('status', 'paid')->where('season_year', $season)->sum('amount_paid'),
            'outstanding' => PaymentExpected::where('status', 'pending')->where('season_year', $season)->sum('amount_due'),
            'upcoming_birthdays' => \App\Models\MemberDetail::whereNotNull('date_of_birth')
                ->whereRaw('DAYOFYEAR(date_of_birth) BETWEEN DAYOFYEAR(NOW()) AND DAYOFYEAR(NOW()) + 30')
                ->with('user')->orderByRaw('DAYOFYEAR(date_of_birth)')->limit(10)->get(),
            'next_events' => Event::where('event_date', '>=', now())->orderBy('event_date')->limit(5)->get(),
        ];

        return view('admin.dashboard.index', compact('stats', 'season'));
    }

    public function exportCsv(Request $request)
    {
        $type = $request->get('type', 'members');

        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename={$type}-export.csv"];

        $callback = function () use ($type) {
            $out = fopen('php://output', 'w');

            if ($type === 'members') {
                fputcsv($out, ['ID', 'First Name', 'Last Name', 'Email', 'Status', 'Role', 'Cert Level', 'Member Since']);
                User::with(['detail', 'status', 'role'])->chunk(100, function ($users) use ($out) {
                    foreach ($users as $u) {
                        fputcsv($out, [$u->id, $u->detail?->first_name, $u->detail?->last_name, $u->primary_email, $u->status?->name, $u->role?->name, $u->detail?->certification_level, $u->detail?->adhesion_year]);
                    }
                });
            } elseif ($type === 'payments') {
                fputcsv($out, ['ID', 'Member', 'Type', 'Amount Due', 'Amount Paid', 'Status', 'Communication']);
                PaymentExpected::with('user.detail')->chunk(100, function ($payments) use ($out) {
                    foreach ($payments as $p) {
                        fputcsv($out, [$p->id, $p->user?->name, $p->type, $p->amount_due, $p->amount_paid, $p->status, $p->communication]);
                    }
                });
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
