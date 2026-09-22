<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportLedgerStatementRequest;
use App\Models\LedgerOperation;
use App\Models\LedgerTag;
use App\Models\LedgerTransaction;
use App\Services\LedgerStatementImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LedgerController extends Controller
{
    public function __construct(private LedgerStatementImportService $importer) {}

    public function index(Request $request): View
    {
        $transactions = LedgerTransaction::with(['counterparty', 'operation', 'tags'])
            ->unconfirmed()
            ->when($request->filled('state'), fn ($q) => $q->where('state', $request->string('state')))
            ->orderByDesc('transaction_date')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        $stateCounts = LedgerTransaction::unconfirmed()->selectRaw('state, count(*) c')->groupBy('state')->pluck('c', 'state');

        // Grouped by (source_file, statement_no), not statement_no alone — two different
        // imports both number their statements 1, 2, 3..., so grouping on the number alone
        // merged unrelated periods (e.g. January of two different years) into one row.
        $statements = LedgerTransaction::selectRaw('source_file, statement_no, min(transaction_date) mn, max(transaction_date) mx, count(*) c, sum(amount) net')
            ->whereNotNull('statement_no')->groupBy('source_file', 'statement_no')->orderByDesc('mn')->get();

        return view('admin.ledger.index', [
            'transactions' => $transactions,
            'stateCounts' => $stateCounts,
            'statements' => $statements,
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

    public function confirm(Request $request, LedgerTransaction $transaction): RedirectResponse
    {
        $transaction->update(['confirmed_at' => now(), 'confirmed_by' => $request->user()->id]);

        return back()->with('success', __('Confirmed.'));
    }

    public function bulkConfirm(Request $request): RedirectResponse
    {
        $v = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        // Bulk action never confirms an amber/red/unmatched line — only what the classifier
        // already trusts. The checkbox is disabled for anything else, but that alone made a
        // 0-confirmed submission (every selected row ineligible) look like the button doing
        // nothing — say explicitly when that's why the count is low.
        $count = LedgerTransaction::whereIn('id', $v['ids'])
            ->whereIn('state', [LedgerTransaction::STATE_EXPECTED, LedgerTransaction::STATE_RECOGNISED, LedgerTransaction::STATE_LOOP])
            ->update(['confirmed_at' => now(), 'confirmed_by' => $request->user()->id]);

        $skipped = count($v['ids']) - $count;
        $message = __(':count line(s) confirmed.', ['count' => $count]);
        if ($skipped > 0) {
            $message .= ' '.__(':skipped not confirmed — only green (Expected / Recognised / Paired) lines can be bulk-confirmed; amber and red need individual review.', ['skipped' => $skipped]);
        }

        return back()->with($count > 0 ? 'success' : 'warning', $message);
    }

    public function tag(Request $request, LedgerTransaction $transaction): RedirectResponse
    {
        $v = $request->validate(['tag_id' => 'required|exists:ledger_tags,id', 'value' => 'nullable|string|max:255']);
        $transaction->tags()->syncWithoutDetaching([$v['tag_id'] => ['value' => $v['value'] ?? null]]);

        return back()->with('success', __('Tag applied.'));
    }

    public function untag(LedgerTransaction $transaction, LedgerTag $tag): RedirectResponse
    {
        $transaction->tags()->detach($tag->id);

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
    public function createTag(Request $request, LedgerTransaction $transaction): RedirectResponse
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

        return back()->with('success', __('Tag ":label" created and applied.', ['label' => $tag->label]));
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
        }

        return back()->with('success', __('Tag applied to :count line(s).', ['count' => count($v['ids'])]));
    }

    /** Assign to an existing operation, or create one on the fly (a suggested group name becomes a one-click operation). */
    public function assignOperation(Request $request, LedgerTransaction $transaction): RedirectResponse
    {
        $v = $request->validate([
            'operation_id' => 'nullable|exists:ledger_operations,id',
            'new_name' => 'nullable|string|max:255',
            'new_kind' => 'nullable|in:'.implode(',', [LedgerOperation::KIND_LOOP, LedgerOperation::KIND_TRIP, LedgerOperation::KIND_COST_CENTRE, LedgerOperation::KIND_OTHER]),
        ]);

        $operationId = $v['operation_id'] ?? null;
        if (! $operationId && ! empty($v['new_name'])) {
            // firstOrCreate, not create: each row with the same suggested group posts
            // "new_name" independently, and the page isn't reloaded between accepts, so
            // a second row accepting "Cap Vert" before the first page refresh must reuse
            // the operation the first row just created, not spawn a duplicate.
            $operationId = LedgerOperation::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($v['new_name'])])
                ->first()?->id
                ?? LedgerOperation::create(['name' => $v['new_name'], 'kind' => $v['new_kind'] ?? LedgerOperation::KIND_OTHER])->id;
        }

        $transaction->update(['operation_id' => $operationId]);

        return back()->with('success', __('Assigned.'));
    }
}
