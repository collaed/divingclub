<x-admin-layout :title="__('Ledger')">
<style>
    .lg-pill { display:inline-flex; align-items:center; gap:.3rem; padding:.1rem .55rem; border-radius:999px; font-size:.75rem; font-weight:600; white-space:nowrap; }
    .lg-expected   { --bg:#146c43; --fg:#fff; }
    .lg-recognised { --bg:#d3f0dc; --fg:#14532d; }
    .lg-confirm    { --bg:#fff0bd; --fg:#664d03; }
    .lg-unknown    { --bg:#f8d7da; --fg:#842029; }
    .lg-loop       { --bg:#cff4fc; --fg:#055160; }
    .lg-pill { background:var(--bg); color:var(--fg); }
    tr.lg-row > td:first-child { border-left:5px solid var(--bg); }
    [data-bs-theme="dark"] .lg-recognised { --bg:#1d4a2c; --fg:#c9f0d5; } [data-bs-theme="dark"] .lg-confirm { --bg:#5c4700; --fg:#ffe69c; }
    [data-bs-theme="dark"] .lg-unknown { --bg:#58151c; --fg:#f1aeb5; } [data-bs-theme="dark"] .lg-loop { --bg:#032830; --fg:#9eeaf9; }
    .lg-chip { display:inline-block; padding:.05rem .45rem; border-radius:.35rem; font-size:.72rem; background:var(--bs-tertiary-bg); border:1px solid var(--bs-border-color); }
    .lg-tag { display:inline-block; padding:0 .4rem; border-radius:.3rem; font-size:.72rem; background:var(--bs-info-bg-subtle); color:var(--bs-info-text-emphasis); border:1px solid var(--bs-info-border-subtle); }
    .lg-var-empty { background:transparent; border:1px dashed var(--bs-warning-border-subtle); color:var(--bs-warning-text-emphasis); }
    {{-- Red/green on a tag: what the club is expected to do, not the amount's actual sign — a
         green "cotisation" tag stays green even applied to a refund line, it's the tag's own
         real-world meaning (see LedgerTag::direction, seeded in LedgerTagSeeder). --}}
    .lg-tag-in  { background:var(--bs-success-bg-subtle); color:var(--bs-success-text-emphasis); border:1px solid var(--bs-success-border-subtle); }
    .lg-tag-out { background:var(--bs-danger-bg-subtle); color:var(--bs-danger-text-emphasis); border:1px solid var(--bs-danger-border-subtle); }
    .lg-tag-remove { border:0; background:transparent; padding:0 0 0 .3rem; margin:0; line-height:1; color:inherit; opacity:.65; font-size:1em; cursor:pointer; }
    .lg-tag-remove:hover { opacity:1; }
    .lg-dot { display:inline-block; width:.5rem; height:.5rem; border-radius:50%; margin-right:.3rem; }
    .lg-dot-in { background:#146c43; } .lg-dot-out { background:#b02a37; }
    .lg-num { font-variant-numeric: tabular-nums; text-align:right; white-space:nowrap; }
    .lg-in { color:#146c43; } .lg-out { color:#b02a37; }
    #lg-bulk { position:sticky; bottom:0; z-index:5; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0">{{ __('Ledger') }}</h4>
        <small class="text-muted">{{ __('Bank movements: classify, tag, and group. Confirming a line records it as reviewed.') }}</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.ledger.operations') }}" class="btn btn-sm btn-outline-secondary">{{ __('Operations') }}</a>
        <a href="{{ route('admin.ledger.review') }}" class="btn btn-sm btn-outline-secondary">{{ __('Review by tag / group') }}</a>
        <form method="POST" action="{{ route('admin.ledger.import') }}" enctype="multipart/form-data" class="d-flex gap-2">
            @csrf
            <input type="file" name="statement" accept=".xlsx" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">{{ __('Import statement') }}</button>
        </form>
    </div>
</div>

{{-- success/warning/validation-error flashes are already rendered by the shared layout --}}

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    @php
        $labels = ['expected' => ['✓✓ '.__('Expected'), 'lg-expected'], 'recognised' => ['✓ '.__('Recognised'), 'lg-recognised'],
                   'confirm' => ['≈ '.__('To confirm'), 'lg-confirm'], 'unknown' => ['? '.__('Unknown'), 'lg-unknown'], 'loop' => ['⇄ '.__('Paired'), 'lg-loop']];
    @endphp
    <a href="{{ route('admin.ledger.index') }}" class="lg-pill lg-chip {{ request('state') ? '' : 'border border-primary' }}">{{ __('All') }} · {{ $stateCounts->sum() }}</a>
    @foreach($labels as $state => [$label, $class])
        <a href="{{ route('admin.ledger.index', ['state' => $state]) }}" class="lg-pill {{ $class }} {{ request('state') === $state ? 'border border-dark' : '' }}">{{ $label }} · {{ $stateCounts[$state] ?? 0 }}</a>
    @endforeach
</div>

{{-- Not wrapped around the table: each row below has its own <form> for
     confirm/tag/assign, and HTML forms cannot nest — a browser silently drops a
     nested <form>'s boundary and submits its controls through the outer one
     instead. The checkboxes below associate with this one by id (form="lg-bulk-form")
     instead, which works from anywhere in the document. --}}
<form id="lg-bulk-form" method="POST" action="{{ route('admin.ledger.bulk-confirm') }}">@csrf</form>
{{-- Shared by every row's group/tag combobox — an <input list> picks up suggestions
     from whichever <datalist> its "list" attribute names, so one copy here is enough. --}}
<datalist id="lg-operations-list">
    @foreach($operations as $op)<option value="{{ $op->name }}">@endforeach
</datalist>
<datalist id="lg-tags-list">
    @foreach($fixedTags->merge($variableTags) as $tag)<option value="{{ $tag->label }}">@endforeach
</datalist>
<div class="card dc-card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th></th><th>{{ __('Date') }}</th><th>{{ __('Counterparty · communication') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('State') }}</th><th>{{ __('Category') }}</th><th>{{ __('Tags') }}</th><th>{{ __('Group') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($transactions as $tx)
                @php [$label, $class] = $labels[$tx->state] ?? ['?', 'lg-unknown']; @endphp
                @php
                    // Bulk-confirm silently ignores anything not already green (see
                    // LedgerController::bulkConfirm) — disabling the checkbox here instead
                    // of letting it submit and do nothing. Caught live: a bureau member
                    // selected amber rows, got "0 line(s) confirmed.", and read that as the
                    // button being broken rather than as "nothing here was eligible."
                    $bulkEligible = in_array($tx->state, [\App\Models\LedgerTransaction::STATE_EXPECTED, \App\Models\LedgerTransaction::STATE_RECOGNISED, \App\Models\LedgerTransaction::STATE_LOOP], true);
                @endphp
                <tr class="lg-row {{ $class }}" data-ledger-row>
                    <td>
                        <input type="checkbox" name="ids[]" value="{{ $tx->id }}" form="lg-bulk-form" class="form-check-input"
                               @disabled(! $bulkEligible)
                               @unless($bulkEligible) title="{{ __('Only green (Expected / Recognised / Paired) lines can be bulk-confirmed — review this one individually.') }}" @endunless>
                    </td>
                    <td class="small">{{ $tx->transaction_date->format('d/m/Y') }}</td>
                    <td style="min-width:14rem">
                        <strong>{{ $tx->counterparty?->name ?? $tx->counterparty_name ?? __('Unknown') }}</strong>
                        <div class="small text-muted">{{ $tx->communication() ?: '—' }}</div>
                        @if($tx->state_reason)<div class="small text-muted fst-italic">{{ $tx->state_reason }}</div>@endif
                    </td>
                    <td class="lg-num {{ $tx->amount >= 0 ? 'lg-in' : 'lg-out' }}">{{ $tx->amount >= 0 ? '+' : '−' }}{{ number_format(abs((float) $tx->amount), 2, ',', ' ') }}</td>
                    <td><span class="lg-pill {{ $class }}">{{ $label }}</span></td>
                    <td>@if($tx->category)<span class="lg-chip" title="{{ config('ledger.categories')[$tx->category] ?? '' }}">{{ $tx->category }}</span>@endif</td>
                    <td>
                        @foreach($tx->tags as $tag)
                            @php
                                $dirClass = $tag->direction === 'in' ? 'lg-tag-in' : ($tag->direction === 'out' ? 'lg-tag-out' : ($tag->pivot->value ? '' : ($tag->kind === 'variable' ? 'lg-var-empty' : '')));
                            @endphp
                            <form method="POST" action="{{ route('admin.ledger.tag.remove', [$tx, $tag]) }}" class="d-inline-block mb-1">
                                @csrf @method('DELETE')
                                <span class="lg-tag {{ $dirClass }}">
                                    #{{ $tag->label }}{{ $tag->pivot->value ? ': '.$tag->pivot->value : '' }}
                                    <button type="submit" class="lg-tag-remove" aria-label="{{ __('Remove tag') }}" title="{{ __('Remove tag') }}">×</button>
                                </span>
                            </form>
                        @endforeach
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-outline-secondary py-0 px-1 dropdown-toggle" type="button" data-bs-toggle="dropdown">+</button>
                            <div class="dropdown-menu p-2" style="min-width:16rem">
                                @foreach($fixedTags as $tag)
                                    <form method="POST" action="{{ route('admin.ledger.tag', $tx) }}" class="mb-1">
                                        @csrf<input type="hidden" name="tag_id" value="{{ $tag->id }}">
                                        <button type="submit" class="dropdown-item small">
                                            @if($tag->direction === 'in')<span class="lg-dot lg-dot-in" title="{{ __('Club receives') }}"></span>
                                            @elseif($tag->direction === 'out')<span class="lg-dot lg-dot-out" title="{{ __('Club pays') }}"></span>
                                            @endif
                                            #{{ $tag->label }}
                                        </button>
                                    </form>
                                @endforeach
                                <hr class="my-1">
                                @foreach($variableTags as $tag)
                                    <form method="POST" action="{{ route('admin.ledger.tag', $tx) }}" class="d-flex gap-1 mb-1 px-2">
                                        @csrf<input type="hidden" name="tag_id" value="{{ $tag->id }}">
                                        <input type="text" name="value" class="form-control form-control-sm" placeholder="{{ $tag->label }}">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Set') }}</button>
                                    </form>
                                @endforeach
                                <hr class="my-1">
                                <form method="POST" action="{{ route('admin.ledger.tag.create', $tx) }}" class="d-flex flex-wrap gap-1 px-2">
                                    @csrf
                                    <input type="text" name="label" list="lg-tags-list" class="form-control form-control-sm" style="width:8rem" placeholder="{{ __('New tag…') }}" maxlength="60" required>
                                    <select name="direction" class="form-select form-select-sm" style="width:auto" aria-label="{{ __('Money direction (only used when creating)') }}">
                                        <option value="">{{ __('Neutral') }}</option>
                                        <option value="in">{{ __('Club receives') }}</option>
                                        <option value="out">{{ __('Club pays') }}</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ __('Add this tag to the list and apply it here') }}">{{ __('Add') }}</button>
                                </form>
                            </div>
                        </div>
                    </td>
                    <td style="min-width:11rem">
                        {{-- Usually one, but exceptionally more than one (e.g. a single van-rental
                             invoice split between two outings) — so every assigned group shows,
                             removable, and the box to add another always stays available. --}}
                        @foreach($tx->operations as $op)
                            <form method="POST" action="{{ route('admin.ledger.operation.remove', [$tx, $op]) }}" class="d-inline-block mb-1">
                                @csrf @method('DELETE')
                                <span class="lg-chip">{{ $op->name }}<button type="submit" class="lg-tag-remove" aria-label="{{ __('Remove from :name', ['name' => $op->name]) }}" title="{{ __('Remove from :name', ['name' => $op->name]) }}">×</button></span>
                            </form>
                        @endforeach
                        @if($tx->suggested_group && ! $tx->operations->contains('name', $tx->suggested_group))
                            <div class="small text-muted">{{ __('Suggested:') }} {{ $tx->suggested_group }}</div>
                        @endif
                        <form method="POST" action="{{ route('admin.ledger.assign-operation', $tx) }}" class="d-flex gap-1 mt-1">
                            @csrf
                            <input type="text" name="new_name" list="lg-operations-list" class="form-control form-control-sm" style="width:9rem"
                                   value="{{ $tx->operations->isEmpty() ? $tx->suggested_group : '' }}" placeholder="{{ $tx->operations->isEmpty() ? __('Type or pick a group…') : __('Add another…') }}">
                            <select name="new_kind" class="form-select form-select-sm" style="width:auto" aria-label="{{ __('Kind (only used when creating a new group)') }}">
                                <option value="trip">{{ __('Trip') }}</option>
                                <option value="loop">{{ __('Loop') }}</option>
                                <option value="cost_centre">{{ __('Cost centre') }}</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Assign') }}</button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.ledger.confirm', $tx) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success">{{ __('Confirm') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">{{ __('Nothing to review — import a statement to get started.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div id="lg-bulk" class="card-footer bg-body-secondary d-flex justify-content-between align-items-center flex-wrap gap-2">
        <button type="submit" form="lg-bulk-form" class="btn btn-sm btn-success">{{ __('Confirm the selected lines (green only)') }}</button>
        {{ $transactions->links() }}
    </div>
</div>

@if($statements->isNotEmpty())
<div class="card dc-card mt-3">
    <div class="card-header">{{ __('Statement balance check') }}</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Source file') }}</th><th>{{ __('Statement') }}</th><th>{{ __('Period') }}</th><th class="text-end">{{ __('Lines') }}</th><th class="text-end">{{ __('Net') }}</th></tr></thead>
            <tbody>
            @foreach($statements as $s)
                <tr><td class="small text-muted">{{ $s->source_file }}</td><td>{{ $s->statement_no }}</td><td class="small">{{ \Illuminate\Support\Carbon::parse($s->mn)->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($s->mx)->format('d/m/Y') }}</td>
                    <td class="lg-num">{{ $s->c }}</td><td class="lg-num {{ $s->net >= 0 ? 'lg-in' : 'lg-out' }}">{{ number_format((float) $s->net, 2, ',', ' ') }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
</x-admin-layout>
