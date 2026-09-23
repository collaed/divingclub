<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportLedgerStatementRequest;
use App\Models\LedgerOperation;
use App\Models\LedgerTag;
use App\Models\LedgerTransaction;
use App\Services\LedgerClassificationService;
use App\Services\LedgerStatementImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LedgerController extends Controller
{
    public function __construct(
        private LedgerStatementImportService $importer,
        private LedgerClassificationService $classifier,
    ) {}

    public function index(Request $request): View
    {
        // "Reviewed" browses back through already-confirmed lines (to check or
        // undo a past confirm) instead of the normal to-do inbox — a separate
        // mode on the same screen rather than a second page, so every other
        // filter (state, pagination) still works the same way in both.
        $reviewed = $request->boolean('reviewed');

        $transactions = LedgerTransaction::with(['counterparty', 'operations', 'tags', 'confirmedBy'])
            ->when($reviewed, fn ($q) => $q->confirmed(), fn ($q) => $q->unconfirmed())
            ->when($request->filled('state'), fn ($q) => $q->where('state', $request->string('state')))
            ->when($reviewed, fn ($q) => $q->orderByDesc('confirmed_at'), fn ($q) => $q->orderByDesc('transaction_date'))
            ->orderByDesc('id')
            ->paginate(30)->withQueryString();

        // Grouped by (source_file, statement_no), not statement_no alone — two different
        // imports both number their statements 1, 2, 3..., so grouping on the number alone
        // merged unrelated periods (e.g. January of two different years) into one row.
        $statements = LedgerTransaction::selectRaw('source_file, statement_no, min(transaction_date) mn, max(transaction_date) mx, count(*) c, sum(amount) net')
            ->whereNotNull('statement_no')->groupBy('source_file', 'statement_no')->orderByDesc('mn')->get();

        return view('admin.ledger.index', [
            'transactions' => $transactions,
            'reviewed' => $reviewed,
            'confirmedCount' => LedgerTransaction::confirmed()->count(),
            'stateCounts' => $this->stateCounts($reviewed),
            'statements' => $statements,
            'labels' => $this->translatedLabels(),
            'fixedTags' => LedgerTag::where('kind', LedgerTag::KIND_FIXED)->orderBy('sort_order')->get(),
            'variableTags' => LedgerTag::where('kind', LedgerTag::KIND_VARIABLE)->orderBy('sort_order')->get(),
            'operations' => LedgerOperation::orderBy('name')->get(),
        ]);
    }

    public function operations(): View
    {
        $operations = LedgerOperation::withCount('transactions')->with('transactions')->orderByDesc('created_at')->get();

        return view('admin.ledger.operations', ['operations' => $operations]);
    }

    /**
     * Pick any number of tags and/or groups from the cloud below and see every
     * matching transaction (a line matches if it carries any selected tag OR
     * belongs to any selected group) with a running +/− sum — the way to check
     * whether a group of expenses balances out, or add up everything under one
     * tag across the whole ledger, confirmed or not.
     */
    public function review(Request $request): View
    {
        $tagIds = array_filter(array_map('intval', (array) $request->input('tags', [])));
        $operationIds = array_filter(array_map('intval', (array) $request->input('operations', [])));

        $transactions = collect();
        if ($tagIds !== [] || $operationIds !== []) {
            $transactions = LedgerTransaction::with(['counterparty', 'operations', 'tags'])
                ->where(function ($q) use ($tagIds, $operationIds): void {
                    if ($tagIds !== []) {
                        $q->orWhereHas('tags', fn ($t) => $t->whereIn('ledger_tags.id', $tagIds));
                    }
                    if ($operationIds !== []) {
                        $q->orWhereHas('operations', fn ($o) => $o->whereIn('ledger_operations.id', $operationIds));
                    }
                })
                ->orderByDesc('transaction_date')->get();
        }

        $totalIn = (float) $transactions->filter(fn (LedgerTransaction $t): bool => (float) $t->amount > 0)->sum('amount');
        $totalOut = (float) $transactions->filter(fn (LedgerTransaction $t): bool => (float) $t->amount < 0)->sum('amount');

        return view('admin.ledger.review', [
            'tags' => LedgerTag::withCount('transactions')->orderByDesc('transactions_count')->orderBy('label')->get(),
            'operations' => LedgerOperation::withCount('transactions')->orderBy('name')->get(),
            'selectedTagIds' => $tagIds,
            'selectedOperationIds' => $operationIds,
            'transactions' => $transactions,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'net' => $totalIn + $totalOut,
        ]);
    }

    public function import(ImportLedgerStatementRequest $request): RedirectResponse
    {
        $file = $request->file('statement');

        try {
            $result = $this->importer->import($file->getRealPath(), $file->getClientOriginalName(), $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['statement' => $e->getMessage()]);
        }

        $balanceIssues = collect($result['statements'])->reject(fn (array $s): bool => $s['balance_ok'])->pluck('statement');

        $message = __(':imported line(s) imported, :duplicates already known.', ['imported' => $result['imported'], 'duplicates' => $result['duplicates']]);
        if ($balanceIssues->isNotEmpty()) {
            $message .= ' '.__('Balance check failed on statement(s): :list — check for a missing or altered line.', ['list' => $balanceIssues->implode(', ')]);

            return back()->with('warning', $message);
        }

        return back()->with('success', $message);
    }

    public function confirm(Request $request, LedgerTransaction $transaction): RedirectResponse|JsonResponse
    {
        $v = $request->validate([
            'new_name' => 'nullable|string|max:255',
            'new_kind' => 'nullable|in:'.implode(',', [LedgerOperation::KIND_LOOP, LedgerOperation::KIND_TRIP, LedgerOperation::KIND_COST_CENTRE, LedgerOperation::KIND_OTHER]),
        ]);

        // The group box next to a row can hold a suggestion or a typed name the
        // user never explicitly clicked "Assign" on — it looks already "set" in
        // the UI, so confirming applies whatever's showing there too, rather
        // than silently discarding it. Caught live: a bureau member "confirmed
        // the suggestion" by clicking Confirm alone, and the group was lost.
        $operationId = $this->resolveOperationId(null, $v['new_name'] ?? null, $v['new_kind'] ?? null);
        if ($operationId) {
            $transaction->operations()->syncWithoutDetaching([$operationId]);
            $this->classifier->reevaluate($transaction);
        }

        $transaction->update(['confirmed_at' => now(), 'confirmed_by' => $request->user()->id]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'removed' => true, 'id' => $transaction->id, 'stateCounts' => $this->stateCounts()]);
        }

        return back()->with('success', __('Confirmed.'));
    }

    /** Undoes a confirm — reached from the "reviewed" list, for going back on a mistake. */
    public function unconfirm(Request $request, LedgerTransaction $transaction): RedirectResponse|JsonResponse
    {
        $transaction->update(['confirmed_at' => null, 'confirmed_by' => null]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'removed' => true, 'id' => $transaction->id, 'stateCounts' => $this->stateCounts(true)]);
        }

        return back()->with('success', __('Back in the inbox to review again.'));
    }

    public function bulkConfirm(Request $request): RedirectResponse|JsonResponse
    {
        $v = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        // Bulk action never confirms an amber/red/unmatched line — only what the classifier
        // already trusts. The checkbox is disabled for anything else, but that alone made a
        // 0-confirmed submission (every selected row ineligible) look like the button doing
        // nothing — say explicitly when that's why the count is low.
        $eligible = LedgerTransaction::whereIn('id', $v['ids'])
            ->whereIn('state', [LedgerTransaction::STATE_EXPECTED, LedgerTransaction::STATE_RECOGNISED, LedgerTransaction::STATE_LOOP])
            ->pluck('id');
        LedgerTransaction::whereIn('id', $eligible)->update(['confirmed_at' => now(), 'confirmed_by' => $request->user()->id]);

        $count = $eligible->count();
        $skipped = count($v['ids']) - $count;
        $message = __(':count line(s) confirmed.', ['count' => $count]);
        if ($skipped > 0) {
            $message .= ' '.__(':skipped not confirmed — only green (Expected / Recognised / Paired) lines can be bulk-confirmed; amber and red need individual review.', ['skipped' => $skipped]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'removedIds' => $eligible->values(), 'message' => $message, 'success' => $count > 0, 'stateCounts' => $this->stateCounts()]);
        }

        return back()->with($count > 0 ? 'success' : 'warning', $message);
    }

    public function tag(Request $request, LedgerTransaction $transaction): RedirectResponse|JsonResponse
    {
        $v = $request->validate(['tag_id' => 'required|exists:ledger_tags,id', 'value' => 'nullable|string|max:255']);
        $transaction->tags()->syncWithoutDetaching([$v['tag_id'] => ['value' => $v['value'] ?? null]]);
        $this->classifier->reevaluate($transaction);

        if ($request->expectsJson()) {
            return $this->rowResponse($transaction, __('Tag applied.'));
        }

        return back()->with('success', __('Tag applied.'));
    }

    public function untag(Request $request, LedgerTransaction $transaction, LedgerTag $tag): RedirectResponse|JsonResponse
    {
        $transaction->tags()->detach($tag->id);
        $this->classifier->reevaluate($transaction);

        if ($request->expectsJson()) {
            return $this->rowResponse($transaction, __('Tag removed.'));
        }

        return back()->with('success', __('Tag removed.'));
    }

    /**
     * A bureau_master isn't stuck with the seeded starter set: typing a label that
     * doesn't already exist creates it (matched case-insensitively against the
     * existing list first, so a near-retype reuses rather than duplicates) and
     * applies it to this row in the same step. Only bureau_master can reach the
     * ledger at all, so there's no separate treasurer-approval step — creating it
     * here is the approval.
     */
    public function createTag(Request $request, LedgerTransaction $transaction): RedirectResponse|JsonResponse
    {
        $v = $request->validate([
            'label' => 'required|string|max:60',
            'kind' => 'nullable|in:'.implode(',', [LedgerTag::KIND_FIXED, LedgerTag::KIND_VARIABLE]),
            'direction' => 'nullable|in:'.implode(',', [LedgerTag::DIRECTION_IN, LedgerTag::DIRECTION_OUT]),
        ]);

        $tag = LedgerTag::whereRaw('LOWER(label) = ?', [mb_strtolower($v['label'])])->first()
            ?? LedgerTag::create([
                'slug' => $this->uniqueTagSlug($v['label']),
                'label' => $v['label'],
                'kind' => $v['kind'] ?? LedgerTag::KIND_FIXED,
                'direction' => $v['direction'] ?? null,
                'sort_order' => (int) LedgerTag::max('sort_order') + 1,
            ]);

        $transaction->tags()->syncWithoutDetaching([$tag->id]);
        $this->classifier->reevaluate($transaction);

        $message = __('Tag ":label" created and applied.', ['label' => $tag->label]);
        if ($request->expectsJson()) {
            return $this->rowResponse($transaction, $message);
        }

        return back()->with('success', $message);
    }

    /** A clean slug from the label; a short suffix only if two different labels would otherwise collide. */
    private function uniqueTagSlug(string $label): string
    {
        $base = Str::slug($label, '_') ?: 'tag';
        $slug = $base;
        for ($i = 2; LedgerTag::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}_{$i}";
        }

        return $slug;
    }

    public function bulkTag(Request $request): RedirectResponse
    {
        $v = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer', 'tag_id' => 'required|exists:ledger_tags,id', 'value' => 'nullable|string|max:255']);
        foreach (LedgerTransaction::whereIn('id', $v['ids'])->get() as $tx) {
            $tx->tags()->syncWithoutDetaching([$v['tag_id'] => ['value' => $v['value'] ?? null]]);
            $this->classifier->reevaluate($tx);
        }

        return back()->with('success', __('Tag applied to :count line(s).', ['count' => count($v['ids'])]));
    }

    /**
     * Attaches an operation — doesn't replace any the line already has, since a
     * transaction can exceptionally belong to more than one (e.g. one van-rental
     * invoice split between two separate outings). Creates one on the fly if
     * "new_name" doesn't match an existing operation (a suggested group name
     * becomes a one-click operation).
     */
    public function assignOperation(Request $request, LedgerTransaction $transaction): RedirectResponse|JsonResponse
    {
        $v = $request->validate([
            'operation_id' => 'nullable|exists:ledger_operations,id',
            'new_name' => 'nullable|string|max:255',
            'new_kind' => 'nullable|in:'.implode(',', [LedgerOperation::KIND_LOOP, LedgerOperation::KIND_TRIP, LedgerOperation::KIND_COST_CENTRE, LedgerOperation::KIND_OTHER]),
        ]);

        $operationId = $this->resolveOperationId($v['operation_id'] ?? null, $v['new_name'] ?? null, $v['new_kind'] ?? null);

        if ($operationId) {
            $transaction->operations()->syncWithoutDetaching([$operationId]);
            $this->classifier->reevaluate($transaction);
        }

        if ($request->expectsJson()) {
            return $this->rowResponse($transaction, __('Assigned.'));
        }

        return back()->with('success', __('Assigned.'));
    }

    public function removeOperation(Request $request, LedgerTransaction $transaction, LedgerOperation $operation): RedirectResponse|JsonResponse
    {
        $transaction->operations()->detach($operation->id);
        $this->classifier->reevaluate($transaction);

        $message = __('Removed from :name.', ['name' => $operation->name]);
        if ($request->expectsJson()) {
            return $this->rowResponse($transaction, $message);
        }

        return back()->with('success', $message);
    }

    /**
     * An explicit operation id wins; otherwise firstOrCreate by name (case-
     * insensitive) — not create, since two rows can post the same "new_name"
     * independently (e.g. two lines both accepting a "Cap Vert" suggestion)
     * before either page refreshes, and must share one operation, not spawn
     * a duplicate.
     */
    private function resolveOperationId(?int $operationId, ?string $newName, ?string $newKind): ?int
    {
        if ($operationId) {
            return $operationId;
        }

        if (empty($newName)) {
            return null;
        }

        return LedgerOperation::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($newName)])
            ->first()?->id
            ?? LedgerOperation::create(['name' => $newName, 'kind' => $newKind ?? LedgerOperation::KIND_OTHER])->id;
    }

    /** The re-rendered row (fresh state/tags/groups) plus updated pill counts, for an in-place swap. */
    private function rowResponse(LedgerTransaction $transaction, string $message): JsonResponse
    {
        $transaction->refresh()->load(['counterparty', 'operations', 'tags']);

        $html = view('admin.ledger._row', [
            'tx' => $transaction,
            'labels' => $this->translatedLabels(),
            'fixedTags' => LedgerTag::where('kind', LedgerTag::KIND_FIXED)->orderBy('sort_order')->get(),
            'variableTags' => LedgerTag::where('kind', LedgerTag::KIND_VARIABLE)->orderBy('sort_order')->get(),
        ])->render();

        return response()->json(['ok' => true, 'html' => $html, 'message' => $message, 'stateCounts' => $this->stateCounts()]);
    }

    /** @return array<string, int> */
    private function stateCounts(bool $reviewed = false): array
    {
        $counts = LedgerTransaction::query()
            ->when($reviewed, fn ($q) => $q->confirmed(), fn ($q) => $q->unconfirmed())
            ->selectRaw('state, count(*) c')->groupBy('state')->pluck('c', 'state')->all();
        $counts['all'] = array_sum($counts);

        return $counts;
    }

    /** @return array<string, array{string, string}> */
    private function translatedLabels(): array
    {
        return [
            'expected' => ['✓✓ '.__('Expected'), 'lg-expected'],
            'recognised' => ['✓ '.__('Recognised'), 'lg-recognised'],
            'confirm' => ['≈ '.__('To confirm'), 'lg-confirm'],
            'unknown' => ['? '.__('Unknown'), 'lg-unknown'],
            'loop' => ['⇄ '.__('Paired'), 'lg-loop'],
        ];
    }
}
