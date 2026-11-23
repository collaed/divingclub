<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailAliasDefinition;
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
                'count' => MailAliasCriteria::matchingUsers($d->criteria)->count(),
            ]);

        return view('admin.mail-aliases.index', [
            'definitions' => $definitions,
            'options' => MailAliasCriteria::options(),
        ]);
    }

    /** Live preview for a draft (not yet saved) checkbox selection. */
    public function preview(Request $request): JsonResponse
    {
        $v = $request->validate([
            'criteria' => 'array',
            'criteria.*' => 'string|in:'.implode(',', array_keys(MailAliasCriteria::options())),
        ]);

        $query = MailAliasCriteria::matchingUsers($v['criteria'] ?? [])->with('detail');
        $count = $query->count();
        $members = $query->limit(self::MAX_PREVIEW_ROWS)->get()
            ->map(fn ($u) => ['name' => $u->name, 'email' => $u->primary_email]);

        return response()->json(['count' => $count, 'members' => $members, 'truncated' => $count > self::MAX_PREVIEW_ROWS]);
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
            'criteria' => 'required|array|min:1',
            'criteria.*' => 'string|in:'.implode(',', array_keys(MailAliasCriteria::options())),
            'auth_level' => ['required', 'in:'.implode(',', array_keys(MailAliasDefinition::AUTH_LEVELS))],
            'enabled' => 'sometimes|boolean',
        ]);

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
