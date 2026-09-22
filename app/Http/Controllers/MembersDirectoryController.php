<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class MembersDirectoryController extends Controller
{
    use PaginatesFromRequest;

    /** Age filter option => cutoff in years from today. "o18" is the one "and over" option; the rest are "under". */
    private const AGE_FILTERS = ['u12' => 12, 'u14' => 14, 'u16' => 16, 'u18' => 18, 'o18' => 18];

    public function directory(Request $request): View|Response
    {
        // Pending-approval accounts must not see other members' details.
        abort_unless(auth()->user()->isConfirmed(), 403);

        // Member-facing directory: former/inactive members are never shown
        // here (this is not a filter that can be turned off — the admin
        // roster is the place to look up former members). Unconditional, so
        // it also can't be bypassed by a crafted status_id in the URL.
        $inactiveIds = MemberStatus::inactiveIds();

        $query = User::with(['detail', 'roles', 'status'])
            ->whereHas('detail', fn ($q) => $q->whereNotNull('first_name'))
            ->where(function ($q) use ($inactiveIds): void {
                $q->whereNull('status_id')->orWhereNotIn('status_id', $inactiveIds->all());
            });

        // Text search
        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('detail', fn ($q) => $q->where(function ($w) use ($s): void {
                $w->whereRaw('LOWER(first_name) like ?', ['%'.strtolower($s).'%'])
                    ->orWhereRaw('LOWER(last_name) like ?', ['%'.strtolower($s).'%']);
            }));
        }

        // Status filter narrows within current members only. "active" is a
        // legacy virtual value from bookmarked links — same as no filter now
        // that former members are always excluded above.
        if ($request->filled('status') && $request->status !== 'active') {
            $query->where('status_id', $request->status);
        }

        // Instructor filter
        if ($request->filled('instructor')) {
            if ($request->instructor === '1') {
                $query->whereHas('detail', fn ($q) => $q->where('active_instructor', true));
            } else {
                $query->whereHas('detail', fn ($q) => $q->where('active_instructor', false)->orWhereNull('active_instructor'));
            }
        }

        // Age filter: each option is its own "under N" / "18 and over" threshold
        // (not a partition into disjoint brackets) — matches the ages the
        // club's own course levels and badges are gated at.
        if ($request->filled('age') && array_key_exists($request->age, self::AGE_FILTERS)) {
            $cutoff = now()->subYears(self::AGE_FILTERS[$request->age])->format('Y-m-d');
            $query->whereHas('detail', fn ($q) => $request->age === 'o18'
                ? $q->where('date_of_birth', '<=', $cutoff)
                : $q->where('date_of_birth', '>', $cutoff));
        }

        // Level filter: a level code (e.g. "N1") can live in either of two free-text
        // fields depending on how the member's data was entered — the legacy scuba
        // certification_level, or apnea_level for freediving — so it matches whichever
        // one holds it, not a fixed vocabulary of "the right" field.
        if ($request->filled('level')) {
            $query->whereHas('detail', fn ($q) => $q->where('certification_level', $request->level)->orWhere('apnea_level', $request->level));
        }

        $sortable = ['last_name', 'certification_level', 'adhesion_year'];
        $sort = in_array($request->sort, $sortable) ? $request->sort : 'last_name';
        $dir = $request->dir === 'desc' ? 'desc' : 'asc';

        $query->join('member_details', 'users.id', '=', 'member_details.user_id')
            ->orderBy("member_details.{$sort}", $dir)
            ->select('users.*');

        $members = $query->paginate($this->perPage(50))->withQueryString();
        $statuses = MemberStatus::whereNotIn('slug', MemberStatus::inactiveSlugs())->orderBy('name')->get();

        if ($request->ajax()) {
            return view('members._directory_rows', compact('members'));
        }

        return view('members.directory', compact('members', 'statuses') + ['levels' => $this->levelOptions()]);
    }

    /** Every distinct level value in use, from either field, for the filter dropdown. */
    private function levelOptions(): Collection
    {
        $scuba = MemberDetail::whereNotNull('certification_level')->distinct()->pluck('certification_level');
        $apnea = MemberDetail::whereNotNull('apnea_level')->distinct()->pluck('apnea_level');

        return $scuba->merge($apnea)->filter()->unique()->sort()->values();
    }

    public function trombinoscope(): View
    {
        abort_unless(auth()->user()->isConfirmed(), 403);

        $inactiveIds = MemberStatus::inactiveIds();

        $members = User::with('detail')
            ->whereHas('detail', fn ($q) => $q->whereNotNull('avatar_path')->whereNotNull('first_name'))
            ->where(fn ($q) => $q->whereNull('status_id')->orWhereNotIn('status_id', $inactiveIds->all()))
            ->get()
            ->sortBy(fn ($u) => $u->detail?->last_name);

        $viewerHasPhoto = auth()->user()->detail?->avatar_path;

        return view('members.trombinoscope', compact('members', 'viewerHasPhoto'));
    }
}
