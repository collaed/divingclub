<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankTransaction;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\EquipmentMaintenance;
use App\Models\Event;
use App\Models\ExternalRegistration;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\ScheduleHeartbeat;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $season = $request->get('season', date('Y'));

        $stats = [
            'total_members' => User::count(),
            'members_by_status' => MemberStatus::withCount('users')->get()->map(fn ($s) => ['name' => $s->name, 'count' => $s->users_count]),
            'new_members_this_year' => User::whereYear('created_at', $season)->count(),
            'events_count' => Event::whereYear('event_date', $season)->count(),
            'avg_attendance' => round(Event::whereYear('event_date', $season)->withCount('confirmedRegistrations')->get()->avg('confirmed_registrations_count') ?? 0, 1),
            'equipment_by_status' => Equipment::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status'),
            'certs_expiring_30d' => Document::where('category', 'medical')->where('is_current', true)->whereBetween('expiry_date', [now(), now()->addDays(30)])->count(),
            'revenue' => PaymentExpected::where('status', 'paid')->where('season_year', $season)->sum('amount_paid'),
            'outstanding' => PaymentExpected::where('status', 'pending')->where('season_year', $season)->sum('amount_due'),
            'upcoming_birthdays' => MemberDetail::whereNotNull('date_of_birth')
                ->whereBetween(
                    \DB::raw(config('database.default') === 'pgsql'
                        ? 'EXTRACT(DOY FROM date_of_birth)'
                        : 'DAYOFYEAR(date_of_birth)'),
                    [\DB::raw(config('database.default') === 'pgsql' ? 'EXTRACT(DOY FROM NOW())' : 'DAYOFYEAR(NOW())'),
                        \DB::raw(config('database.default') === 'pgsql' ? 'EXTRACT(DOY FROM NOW()) + 30' : 'DAYOFYEAR(NOW()) + 30')]
                )
                ->orderByRaw(config('database.default') === 'pgsql'
                    ? 'EXTRACT(DOY FROM date_of_birth)'
                    : 'DAYOFYEAR(date_of_birth)')
                ->with('user')->limit(10)->get(),
            'next_events' => Event::where('event_date', '>=', now())->orderBy('event_date')->limit(20)->get()
                ->unique('title')->take(3)->values(),
        ];

        // Bureau worklist: pending actions
        $worklist = [
            'unverified_certs' => Document::where('category', 'medical')->where('is_current', true)->whereNull('verified_at')->count(),
            'expiring_certs' => Document::where('category', 'medical')->where('is_current', true)->whereBetween('expiry_date', [now(), now()->addDays(30)])->count(),
            'pending_payments' => PaymentExpected::where('status', 'pending')->where('season_year', $season)->count(),
            'pending_external_regs' => ExternalRegistration::where('status', 'pending')->count(),
            'unverified_emails' => User::whereNull('email_verified_at')->count(),
            'missing_medical' => User::whereDoesntHave('documents', fn ($q) => $q->where('category', 'medical')->where('is_current', true))->whereHas('status', fn ($q) => $q->where('slug', 'actif'))->count(),
            'missing_iban' => User::whereHas('detail', fn ($q) => $q->whereNull('iban'))->whereHas('status', fn ($q) => $q->where('slug', 'actif'))->count(),
            'new_members_unconfirmed' => User::whereNull('status_id')->whereNotNull('email_verified_at')->count(),
            'birthdays_14d' => MemberDetail::whereNotNull('date_of_birth')
                ->whereBetween(
                    \DB::raw(config('database.default') === 'pgsql'
                        ? 'EXTRACT(DOY FROM date_of_birth)'
                        : 'DAYOFYEAR(date_of_birth)'),
                    [\DB::raw(config('database.default') === 'pgsql' ? 'EXTRACT(DOY FROM NOW())' : 'DAYOFYEAR(NOW())'),
                        \DB::raw(config('database.default') === 'pgsql' ? 'EXTRACT(DOY FROM NOW()) + 14' : 'DAYOFYEAR(NOW()) + 14')]
                )
                ->with('user')->get(),
            'unmatched_transactions' => BankTransaction::where('status', 'unmatched')->count(),
            'refund_reviews' => PaymentExpected::where('refund_review_needed', true)->count(),
            'overdue_maintenance' => EquipmentMaintenance::where('is_mandatory', true)->whereNull('completed_at')->where('due_date', '<', now())->count(),
            'overdue_loans' => EquipmentLoan::whereNull('returned_at')->where(fn ($q) => $q->where('expected_return_date', '<', now())->orWhere('loaned_at', '<', now()->subDays((int) ThemeSetting::get('equipment_loan_max_days', 30))))->count(),
            'minors_no_guardian' => User::whereHas('detail', fn ($q) => $q->whereNotNull('date_of_birth')
                ->where('date_of_birth', '>', now()->subYears(18)))
                ->whereDoesntHave('guardians')->count(),
        ];

        $heartbeats = ScheduleHeartbeat::all();

        return view('admin.dashboard.index', compact('stats', 'season', 'worklist', 'heartbeats'));
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
