<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Models\MemberStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MembersDirectoryController extends Controller
{
    use PaginatesFromRequest;

    public function directory(Request $request): View|Response
    {
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

        // Age bracket filter
        if ($request->filled('age')) {
            [$min, $max] = explode('-', $request->age);
            $from = now()->subYears((int) $max + 1)->addDay()->format('Y-m-d');
            $to = now()->subYears((int) $min)->format('Y-m-d');
            $query->whereHas('detail', fn ($q) => $q->whereBetween('date_of_birth', [$from, $to]));
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

        return view('members.directory', compact('members', 'statuses'));
    }

    public function trombinoscope(): View
    {
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
