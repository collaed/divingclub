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

<form id="lg-bulk-form" method="POST" action="{{ route('admin.ledger.bulk-confirm') }}">
@csrf
<div class="card dc-card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th></th><th>{{ __('Date') }}</th><th>{{ __('Counterparty · communication') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('State') }}</th><th>{{ __('Category') }}</th><th>{{ __('Tags') }}</th><th>{{ __('Group') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($transactions as $tx)
                @php [$label, $class] = $labels[$tx->state] ?? ['?', 'lg-unknown']; @endphp
                <tr class="lg-row {{ $class }}" data-ledger-row>
                    <td><input type="checkbox" name="ids[]" value="{{ $tx->id }}" form="lg-bulk-form" class="form-check-input"></td>
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
                            <span class="lg-tag {{ $tag->pivot->value ? '' : ($tag->kind === 'variable' ? 'lg-var-empty' : '') }}">#{{ $tag->label }}{{ $tag->pivot->value ? ': '.$tag->pivot->value : '' }}</span>
                        @endforeach
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-outline-secondary py-0 px-1 dropdown-toggle" type="button" data-bs-toggle="dropdown">+</button>
                            <div class="dropdown-menu p-2" style="min-width:16rem">
                                @foreach($fixedTags as $tag)
                                    <form method="POST" action="{{ route('admin.ledger.tag', $tx) }}" class="mb-1">
                                        @csrf<input type="hidden" name="tag_id" value="{{ $tag->id }}">
                                        <button type="submit" class="dropdown-item small">#{{ $tag->label }}</button>
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
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($tx->operation)
                            <span class="lg-chip">{{ $tx->operation->name }}</span>
                        @else
                            <form method="POST" action="{{ route('admin.ledger.assign-operation', $tx) }}" class="d-flex gap-1">
                                @csrf
                                <select name="operation_id" class="form-select form-select-sm" style="width:auto">
                                    <option value="">{{ $tx->suggested_group ? __('Group? ').$tx->suggested_group.'…' : __('Assign…') }}</option>
                                    @foreach($operations as $op)<option value="{{ $op->id }}">{{ $op->name }}</option>@endforeach
                                </select>
                                @if($tx->suggested_group)
                                    <input type="hidden" name="new_name" value="{{ $tx->suggested_group }}">
                                    <input type="hidden" name="new_kind" value="trip">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Yes') }}">✓</button>
                                @else
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Go') }}</button>
                                @endif
                            </form>
                        @endif
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
</form>

@if($statements->isNotEmpty())
<div class="card dc-card mt-3">
    <div class="card-header">{{ __('Statement balance check') }}</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Statement') }}</th><th>{{ __('Period') }}</th><th class="text-end">{{ __('Lines') }}</th><th class="text-end">{{ __('Net') }}</th></tr></thead>
            <tbody>
            @foreach($statements as $s)
                <tr><td>{{ $s->statement_no }}</td><td class="small">{{ \Illuminate\Support\Carbon::parse($s->mn)->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($s->mx)->format('d/m/Y') }}</td>
                    <td class="lg-num">{{ $s->c }}</td><td class="lg-num {{ $s->net >= 0 ? 'lg-in' : 'lg-out' }}">{{ number_format((float) $s->net, 2, ',', ' ') }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
</x-admin-layout>
