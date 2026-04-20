<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MemberStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class MemberController extends Controller
{
    use PaginatesFromRequest;

    public function index(Request $request)
    {
        $query = User::with(['detail', 'roles', 'status']);

        if ($request->filled('search')) {
            $s = $request->search;
            $op = config('database.default') === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($s) {
                $q->where('primary_email', 'ILIKE', "%$s%")
                    ->orWhere('username', 'ILIKE', "%$s%")
                    ->orWhereHas('detail', fn ($q2) => $q2->where('first_name', 'ILIKE', "%$s%")->orWhere('last_name', 'ILIKE', "%$s%"));
            });
        }
        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }
        if ($request->filled('role_id')) {
            $roleName = Role::find($request->role_id)?->name;
            if ($roleName) {
                $query->role($roleName);
            }
        }

        $sortable = ['id' => 'users.id', 'email' => 'primary_email', 'name' => 'primary_email'];
        $sort = $sortable[$request->get('sort')] ?? 'users.id';
        $dir = $request->get('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $members = $query->orderBy($sort, $dir)->paginate($this->perPage(25))->withQueryString();
        $statuses = MemberStatus::orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        return view('admin.members.index', compact('members', 'statuses', 'roles'));
    }

    public function impersonate(User $user)
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'impersonate_start',
            'model_type' => User::class,
            'model_id' => $user->id,
            'new_values' => ['target' => $user->primary_email],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        session([
            'impersonating' => $user->id,
            'impersonating_name' => $user->name,
            'original_user_id' => auth()->id(),
        ]);

        auth()->login($user);

        return redirect()->route('profile.show')->with('success', __('Now impersonating :name', ['name' => $user->name]));
    }

    public function stopImpersonation()
    {
        $originalId = session('original_user_id');
        abort_unless($originalId, 403);
        session()->forget(['impersonating', 'impersonating_name', 'original_user_id']);

        if ($originalId) {
            auth()->loginUsingId($originalId);
        }

        return redirect()->route('admin.members.index')->with('success', __('Impersonation ended.'));
    }
}
