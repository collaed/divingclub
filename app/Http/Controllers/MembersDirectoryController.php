<?php

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
        $query = User::with(['detail', 'roles', 'status'])
            ->whereHas('detail', fn ($q) => $q->whereNotNull('first_name'));

        // Text search
        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('detail', fn ($q) => $q->where(function ($w) use ($s) {
                $w->whereRaw('LOWER(first_name) like ?', ['%'.strtolower($s).'%'])
                    ->orWhereRaw('LOWER(last_name) like ?', ['%'.strtolower($s).'%']);
            }));
        }

        // Status filter — "active" is a virtual group
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $activeStatuses = MemberStatus::whereIn('slug', ['membre_de_droit', 'externe', 'associe', 'assimile'])->pluck('id');
                $query->whereIn('status_id', $activeStatuses);
            } else {
                $query->where('status_id', $request->status);
            }
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
        $statuses = MemberStatus::orderBy('name')->get();

        if ($request->ajax()) {
            return view('members._directory_rows', compact('members'));
        }

        return view('members.directory', compact('members', 'statuses'));
    }

    public function trombinoscope(): View
    {
        $members = User::with('detail')
            ->whereHas('detail', fn ($q) => $q->whereNotNull('avatar_path')->whereNotNull('first_name'))
            ->get()
            ->sortBy(fn ($u) => $u->detail?->last_name);

        $viewerHasPhoto = auth()->user()->detail?->avatar_path;

        return view('members.trombinoscope', compact('members', 'viewerHasPhoto'));
    }
}
