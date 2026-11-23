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
        return $request->validate([
            'tag' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:mail_alias_definitions,tag,'.($existing?->id ?? 'NULL')],
            'label' => 'required|string|max:255',
            'criteria' => 'required|array|min:1',
            'criteria.*' => 'string|in:'.implode(',', array_keys(MailAliasCriteria::options())),
            'auth_level' => 'required|in:bureau,bureau_or_instructor',
            'enabled' => 'sometimes|boolean',
        ]);
    }
}
