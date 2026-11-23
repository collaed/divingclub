<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailAliasDefinition;
use App\Models\MailListContact;
use App\Models\User;
use App\Services\MailAliasCriteria;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MailAliasController extends Controller
{
    private const MAX_PREVIEW_ROWS = 200;

    public function index(): View
    {
        $definitions = MailAliasDefinition::orderBy('label')->get()
            ->map(fn (MailAliasDefinition $d) => [
                'definition' => $d,
                'count' => MailAliasCriteria::matchingUsers($d->criteria, $d->include_user_ids, $d->exclude_user_ids)->count(),
            ]);

        return view('admin.mail-aliases.index', [
            'definitions' => $definitions,
            'options' => MailAliasCriteria::options(),
            'availableUsers' => User::with('detail')->get(),
            'availableContacts' => MailListContact::orderBy('email')->get(),
        ]);
    }

    /** Live preview for a draft (not yet saved) selection. */
    public function preview(Request $request): JsonResponse
    {
        $v = $request->validate([
            'criteria' => 'array',
            'criteria.*' => 'string|in:'.implode(',', array_keys(MailAliasCriteria::options())),
            'include_user_ids' => 'array',
            'include_user_ids.*' => 'integer',
            'exclude_user_ids' => 'array',
            'exclude_user_ids.*' => 'integer',
            'include_contact_ids' => 'array',
            'include_contact_ids.*' => 'integer',
        ]);

        $query = MailAliasCriteria::matchingUsers($v['criteria'] ?? [], $v['include_user_ids'] ?? [], $v['exclude_user_ids'] ?? [])->with('detail');
        $count = $query->count();
        $members = $query->limit(self::MAX_PREVIEW_ROWS)->get()
            ->map(fn ($u) => ['name' => $u->name, 'email' => $u->primary_email]);

        $contacts = MailListContact::whereIn('id', $v['include_contact_ids'] ?? [])->get()
            ->map(fn (MailListContact $c) => ['name' => $c->name ?: $c->email, 'email' => $c->email]);

        $members = $members->concat($contacts);

        return response()->json([
            'count' => $count + $contacts->count(),
            'members' => $members->take(self::MAX_PREVIEW_ROWS)->values(),
            'truncated' => $count > self::MAX_PREVIEW_ROWS,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless(auth()->user()?->hasRole('bureau_master'), 403);

        $v = $this->validated($request);
        $definition = MailAliasDefinition::create($v);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'definition' => $definition]);
        }

        return back()->with('success', __('Mail alias created.'));
    }

    public function update(Request $request, MailAliasDefinition $mailAliasDefinition): RedirectResponse|JsonResponse
    {
        abort_unless(auth()->user()?->hasRole('bureau_master'), 403);

        $v = $this->validated($request, $mailAliasDefinition);
        $mailAliasDefinition->update($v);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'definition' => $mailAliasDefinition->fresh()]);
        }

        return back()->with('success', __('Mail alias updated.'));
    }

    public function destroy(Request $request, MailAliasDefinition $mailAliasDefinition): RedirectResponse|JsonResponse
    {
        abort_unless(auth()->user()?->hasRole('bureau_master'), 403);

        $mailAliasDefinition->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Mail alias deleted.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?MailAliasDefinition $existing = null): array
    {
        $v = $request->validate([
            'tags' => 'required|string|max:500',
            'label' => 'required|string|max:255',
            'criteria' => 'array',
            'criteria.*' => 'string|in:'.implode(',', array_keys(MailAliasCriteria::options())),
            'include_user_ids' => 'array',
            'include_user_ids.*' => 'integer|exists:users,id',
            'exclude_user_ids' => 'array',
            'exclude_user_ids.*' => 'integer|exists:users,id',
            'include_contact_ids' => 'array',
            'include_contact_ids.*' => 'integer|exists:mail_list_contacts,id',
            'auth_level' => ['required', 'in:'.implode(',', array_keys(MailAliasDefinition::AUTH_LEVELS))],
            'enabled' => 'sometimes|boolean',
        ]);

        // Unchecked/emptied criteria or chip lists don't appear in the
        // request at all — default them explicitly so an update actually
        // clears them instead of leaving the old value in place.
        $v['criteria'] ??= [];
        $v['include_user_ids'] ??= [];
        $v['exclude_user_ids'] ??= [];
        $v['include_contact_ids'] ??= [];

        if ($v['criteria'] === [] && $v['include_user_ids'] === [] && $v['include_contact_ids'] === []) {
            throw ValidationException::withMessages([
                'criteria' => __('Tick at least one property, or add at least one named member or contact below.'),
            ]);
        }

        $v['tags'] = $this->normalizedTags($v['tags'], $existing);

        return $v;
    }

    /**
     * Split the comma-separated "tags" input into a deduplicated, slugged
     * list, and reject any tag already claimed by another definition — tags
     * replace a unique DB column, so this is where that uniqueness lives.
     *
     * @return array<int, string>
     */
    private function normalizedTags(string $raw, ?MailAliasDefinition $existing): array
    {
        $tags = collect(explode(',', $raw))
            ->map(fn (string $t) => Str::slug(trim($t), '-'))
            ->filter()
            ->unique()
            ->values();

        if ($tags->isEmpty()) {
            throw ValidationException::withMessages(['tags' => __('Enter at least one tag.')]);
        }

        $clash = MailAliasDefinition::query()
            ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))
            ->get()
            ->first(fn (MailAliasDefinition $d) => array_intersect($tags->all(), $d->tags) !== []);

        if ($clash) {
            throw ValidationException::withMessages([
                'tags' => __('":tag" is already used by the ":label" alias.', [
                    'tag' => implode(', ', array_intersect($tags->all(), $clash->tags)),
                    'label' => $clash->label,
                ]),
            ]);
        }

        return $tags->all();
    }
}
