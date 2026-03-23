<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Models\User;
use Illuminate\Http\Request;

class MembersDirectoryController extends Controller
{
    use PaginatesFromRequest;

    public function directory(Request $request)
    {
        $query = User::with(['detail', 'role', 'status'])
            ->whereHas('detail', fn ($q) => $q->whereNotNull('first_name'));

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('detail', fn ($q) => $q->where(function ($w) use ($s) {
                $w->whereRaw('LOWER(first_name) like ?', ['%'.strtolower($s).'%'])
                    ->orWhereRaw('LOWER(last_name) like ?', ['%'.strtolower($s).'%']);
            }));
        }

        $members = $query->orderByDesc('id')->paginate($this->perPage(30))->withQueryString();

        if ($request->ajax()) {
            return view('members._directory_rows', compact('members'));
        }

        return view('members.directory', compact('members'));
    }

    public function trombinoscope()
    {
        $members = User::with('detail')
            ->whereHas('detail', fn ($q) => $q->whereNotNull('first_name'))
            ->get()
            ->sortBy(fn ($u) => $u->detail?->last_name);

        return view('members.trombinoscope', compact('members'));
    }
}
