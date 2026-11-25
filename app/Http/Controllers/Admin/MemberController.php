<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberByBureauRequest;
use App\Models\AuditLog;
use App\Models\MailForwardingRule;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\StatusSet;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Models\UserEmail;
use App\Notifications\ProfileApprovedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class MemberController extends Controller
{
    use PaginatesFromRequest;

    public function index(Request $request): RedirectResponse|View
    {
        // GDPR-erased accounts are soft-deleted (see GdprController::confirmErasure())
        // and excluded from the normal listing entirely — this is a dedicated view
        // onto just those, so bureau_master can permanently purge them. Backfill any
        // erasure done before soft-deleting was added here — those just anonymized
        // the row in place and left deleted_at null — so every erased account ends
        // up soft-deleted and reachable by TrashController::forceDelete() (which
        // only looks at already-trashed rows), not just the ones erased since.
        if ($request->boolean('erased')) {
            User::whereNull('deleted_at')
                ->where('primary_email', 'like', 'erased-%@erased.local')
                ->update(['deleted_at' => now()]);

            $members = User::onlyTrashed()
                ->where('primary_email', 'like', 'erased-%@erased.local')
                ->orderByDesc('deleted_at')
                ->paginate($this->perPage(25))->withQueryString();

            return view('admin.members.index', [
                'members' => $members,
                'erased' => true,
                'historic' => false,
                'statuses' => MemberStatus::orderBy('name')->get(),
                'statusSets' => StatusSet::with('statuses:id')->orderBy('name')->get(),
                'roles' => Role::orderBy('name')->get(),
            ]);
        }

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
            $query->where(function ($q) use ($s, $op): void {
                $q->where('primary_email', $op, "%$s%")
                    ->orWhere('username', $op, "%$s%")
                    ->orWhereHas('detail', fn ($q2) => $q2->where('first_name', $op, "%$s%")->orWhere('last_name', $op, "%$s%"));
            });
        }
        if ($request->input('status_id') === MemberStatus::ACTIVE_FILTER) {
            $query->active();
        } elseif ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }
        if ($request->filled('role_id')) {
            $roleName = Role::find($request->role_id)?->name;
            if ($roleName) {
                $query->role($roleName);
            }
        }
        if ($request->boolean('active_only')) {
            // Same "in good standing" definition as the Send Email tool's
            // Active Members group — cotisation paid this season, or
            // honoraire — not the literal (near-empty) "Actif" status row.
            $query->active();
        }

        $sortable = ['id' => 'users.id', 'email' => 'primary_email', 'name' => 'primary_email', 'last_seen_at' => 'last_seen_at'];
        $sortKey = $request->get('sort');
        $sort = $sortable[$sortKey] ?? 'users.id';
        $dir = $request->get('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        if ($sort === 'last_seen_at') {
            // Never-seen accounts (null) are the stalest regardless of sort
            // direction: first when showing oldest-first, last when showing
            // newest-first. "IS NULL" is 0/1 on both MySQL and Postgres.
            $nullsDir = $dir === 'asc' ? 'desc' : 'asc';
            $query->orderByRaw("last_seen_at is null {$nullsDir}")->orderBy($sort, $dir);
        } else {
            $query->orderBy($sort, $dir);
        }

        $members = $query->paginate($this->perPage(25))->withQueryString();
        $statuses = MemberStatus::orderBy('name')->get();
        $statusSets = StatusSet::with('statuses:id')->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        return view('admin.members.index', compact('members', 'statuses', 'statusSets', 'roles', 'historic') + ['erased' => false]);
    }

    /**
     * Manual exception to self-registration (no email, joined on paper,
     * etc.). The member is created active — the bureau vouches for them —
     * and gets a "set your password" link rather than the bureau ever
     * choosing one for them.
     */
    public function create(): View
    {
        $statuses = MemberStatus::offered();

        return view('admin.members.create', compact('statuses'));
    }

    public function store(StoreMemberByBureauRequest $request): RedirectResponse
    {
        $v = $request->validated();

        $user = DB::transaction(function () use ($v): User {
            $roleTable = Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
            $memberRoleId = DB::table($roleTable)->where('slug', 'member')->value('id')
                ?? DB::table($roleTable)->where('name', 'member')->value('id')
                ?? 2;

            $user = User::create([
                'primary_email' => $v['email'],
                'password' => Str::random(40),
                'role_id' => $memberRoleId,
                'status_id' => $v['status_id'] ?? MemberStatus::where('slug', 'actif')->value('id'),
                'email_verified_at' => now(),
            ]);
            $user->assignRole('member');

            UserEmail::create([
                'user_id' => $user->id,
                'email' => $v['email'],
                'is_primary' => true,
                'is_verified' => true,
            ]);

            MemberDetail::create([
                'user_id' => $user->id,
                'first_name' => $v['first_name'],
                'last_name' => $v['last_name'],
                'date_of_birth' => $v['date_of_birth'] ?? null,
                'phone_mobile' => $v['phone_mobile'] ?? null,
                'nationality' => $v['nationality'] ?? null,
                'adhesion_year' => now()->year,
            ]);

            return $user;
        });

        Password::sendResetLink(['email' => $user->primary_email]);

        return redirect()->route('admin.profile.show', $user)
            ->with('success', __('Member created — a link to set their password was sent to :email.', ['email' => $user->primary_email]));
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

        $wasUnclassified = $user->status_set_id === null;
        $targetSetId = array_key_exists('status_set_id', $v) ? $v['status_set_id'] : $user->status_set_id;
        $targetStatusId = array_key_exists('status_id', $v) ? $v['status_id'] : $user->status_id;

        if ($targetSetId && $targetStatusId) {
            $set = StatusSet::with('statuses:id')->find($targetSetId);
            $offered = $set?->statuses->pluck('id')->all() ?? [];
            if (! in_array((int) $targetStatusId, $offered, true)) {
                $msg = __('The selected status is not part of the assigned status set.');
                if ($request->expectsJson()) {
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

        // First time a status set is assigned — the bureau's "approval" of a
        // new member's profile, not a later re-classification.
        if ($wasUnclassified && $targetSetId !== null) {
            $user->notify(new ProfileApprovedNotification);
        }

        if ($request->expectsJson()) {
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

    /**
     * Housekeeping soft-delete: bureau_master retires a stale/old member from
     * the active roster. Reversible (see Admin\TrashController::restore()) —
     * permanent purging is still a separate, bureau_master-gated step there.
     */
    public function archive(Request $request, User $user): RedirectResponse|JsonResponse
    {
        abort_unless(auth()->user()?->hasRole('bureau_master'), 403);
        abort_if($user->id === auth()->id(), 422, __('Use account erasure to remove your own account.'));

        $user->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Member archived — find it under Trash to restore or permanently delete.'));
    }

    public function sendReset(User $user): RedirectResponse
    {
        Password::sendResetLink(['email' => $user->primary_email]);

        return back()->with('success', __('Password reset link sent to :email', ['email' => $user->primary_email]));
    }

    /**
     * Activates a personal vanity alias (sas.{name}@...) for a member —
     * mail to it forwards to their own address + the club's shared cc —
     * by creating the same MailForwardingRule row the Email & Forwarding
     * settings page manages, tagged with this member (see
     * MailForwardingRule::$user). A no-op if one already exists. The local
     * part defaults to the member's first name but bureau_master can
     * override it (e.g. to match an address already in use under the old
     * system, or to disambiguate members who share a first name).
     */
    public function activatePersonalAlias(Request $request, User $user): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole('bureau_master'), 403);

        if (MailForwardingRule::where('user_id', $user->id)->exists()) {
            return back()->with('error', __('A personal alias already exists for this member.'));
        }

        $base = ThemeSetting::get('mail_inbound_address', config('club.mail_address', 'club@example.com'));
        $domain = explode('@', $base, 2)[1] ?? 'example.com';
        $localPart = $request->input('local_part');
        $slug = $localPart ? $this->slugLocalPart($localPart) : Str::slug($user->detail?->first_name ?? $user->name);
        $fromAddress = "sas.{$slug}@{$domain}";

        if (MailForwardingRule::where('from_address', $fromAddress)->exists()) {
            return back()->with('error', __('The address :address is already taken by another member.', ['address' => $fromAddress]));
        }

        $ccAddress = ThemeSetting::get('mail_personal_alias_cc', config('club.contact_email'));

        MailForwardingRule::create([
            'user_id' => $user->id,
            'from_address' => $fromAddress,
            'to_addresses' => $ccAddress ? "{$user->primary_email},{$ccAddress}" : $user->primary_email,
            'label' => $user->name,
        ]);

        return back()->with('success', __('Personal alias activated for :name.', ['name' => $user->name]));
    }

    /**
     * Like Str::slug(), but keeps dots, so an admin can type a
     * firstname.lastname local part matching an old-system address exactly.
     */
    private function slugLocalPart(string $value): string
    {
        $value = Str::ascii(trim($value));
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9.]+/', '-', $value) ?? '';
        $value = preg_replace('/-{2,}/', '-', $value) ?? '';

        return trim($value, '-');
    }
}
