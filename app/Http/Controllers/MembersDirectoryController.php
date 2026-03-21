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
            $query->whereHas('detail', fn ($q) => $q->where('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%"));
        }

        $members = $query->orderByDesc('id')->paginate($this->perPage(30))->withQueryString();

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
