<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MemberStatus;
use App\Models\StatusSet;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;

class MemberController extends Controller
{
    use PaginatesFromRequest;

    public function index(Request $request): RedirectResponse|View
    {
        $query = User::with(['detail', 'roles', 'status', 'statusSet']);

        // Default listing hides inactive (former) members. The bureau-only
        // "historic" toggle reveals the full roster including lapsed members.
        $historic = $request->boolean('historic');
        if (! $historic) {
            $inactiveIds = MemberStatus::inactiveIds();
            if ($inactiveIds->isNotEmpty()) {
                $query->where(function ($q) use ($inactiveIds): void {
                    $q->whereNull('status_id')->orWhereNotIn('status_id', $inactiveIds->all());
                });
            }
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $op = config('database.default') === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($s): void {
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
        $statusSets = StatusSet::with('statuses:id')->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        return view('admin.members.index', compact('members', 'statuses', 'statusSets', 'roles', 'historic'));
    }

    /**
     * Inline AJAX auto-save of a member's status set and/or current status from
     * the roster. Enforces that the chosen status belongs to the assigned set.
     * Returns JSON for AJAX and redirects otherwise.
     */
    public function updateStatus(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $v = $request->validate([
            'status_id' => 'sometimes|nullable|integer|exists:member_statuses,id',
            'status_set_id' => 'sometimes|nullable|integer|exists:status_sets,id',
        ]);

        $targetSetId = array_key_exists('status_set_id', $v) ? $v['status_set_id'] : $user->status_set_id;
        $targetStatusId = array_key_exists('status_id', $v) ? $v['status_id'] : $user->status_id;

        if ($targetSetId && $targetStatusId) {
            $set = StatusSet::with('statuses:id')->find($targetSetId);
            $offered = $set?->statuses->pluck('id')->all() ?? [];
            if (! in_array((int) $targetStatusId, $offered, true)) {
                $msg = __('The selected status is not part of the assigned status set.');
                if ($request->ajax()) {
                    return response()->json(['ok' => false, 'message' => $msg], 422);
                }

                return back()->withErrors(['status_id' => $msg]);
            }
        }

        $updates = [];
        if (array_key_exists('status_id', $v)) {
            $updates['status_id'] = $v['status_id'];
        }
        if (array_key_exists('status_set_id', $v)) {
            $updates['status_set_id'] = $v['status_set_id'];
        }
        if ($updates !== []) {
            $user->update($updates);
        }

        if ($request->ajax()) {
            return response()->json(['ok' => true, 'user' => $user->fresh(['status', 'statusSet'])]);
        }

        return back()->with('success', __('Member status updated.'));
    }

    public function impersonate(User $user): RedirectResponse
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

    public function stopImpersonation(): RedirectResponse
    {
        $originalId = session('original_user_id');
        abort_unless($originalId, 403);
        session()->forget(['impersonating', 'impersonating_name', 'original_user_id']);

        /** @phpstan-ignore if.alwaysTrue */
        if ($originalId) {
            auth()->loginUsingId($originalId);
        }

        return redirect()->route('admin.members.index')->with('success', __('Impersonation ended.'));
    }

    public function sendReset(User $user): RedirectResponse
    {
        Password::sendResetLink(['email' => $user->primary_email]);

        return back()->with('success', __('Password reset link sent to :email', ['email' => $user->primary_email]));
    }
}
