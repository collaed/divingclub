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
        <h4 class="mb-0">{{ $reviewed ? __('Ledger — reviewed lines') : __('Ledger') }}</h4>
        <small class="text-muted">
            @if($reviewed)
                {{ __('Already-confirmed lines, most recently reviewed first — undo a confirm to send one back to the inbox.') }}
            @else
                {{ __('Bank movements: classify, tag, and group. Confirming a line records it as reviewed.') }}
            @endif
        </small>
    </div>
    <div class="d-flex gap-2">
        @if($reviewed)
            <a href="{{ route('admin.ledger.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Back to the inbox') }}</a>
        @else
            <a href="{{ route('admin.ledger.index', ['reviewed' => 1]) }}" class="btn btn-sm btn-outline-secondary">{{ __('Reviewed') }} ({{ $confirmedCount }})</a>
        @endif
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
        $reviewedParam = $reviewed ? ['reviewed' => 1] : [];
    @endphp
    <a href="{{ route('admin.ledger.index', $reviewedParam) }}" class="lg-pill lg-chip {{ request('state') ? '' : 'border border-primary' }}">{{ __('All') }} · <span data-state-pill="all">{{ $stateCounts['all'] ?? 0 }}</span></a>
    @foreach($labels as $state => [$label, $class])
        <a href="{{ route('admin.ledger.index', ['state' => $state, ...$reviewedParam]) }}" class="lg-pill {{ $class }} {{ request('state') === $state ? 'border border-dark' : '' }}">{{ $label }} · <span data-state-pill="{{ $state }}">{{ $stateCounts[$state] ?? 0 }}</span></a>
    @endforeach
</div>

{{-- Not wrapped around the table: each row below has its own <form> for
     confirm/tag/assign, and HTML forms cannot nest — a browser silently drops a
     nested <form>'s boundary and submits its controls through the outer one
     instead. The checkboxes below associate with this one by id (form="lg-bulk-form")
     instead, which works from anywhere in the document. --}}
<form id="lg-bulk-form" method="POST" action="{{ route('admin.ledger.bulk-confirm') }}" data-ledger-ajax data-ledger-bulk>@csrf</form>
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
            <thead>
                @if($reviewed)
                    <tr><th>{{ __('Date') }}</th><th>{{ __('Counterparty · communication') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('State') }}</th><th>{{ __('Category') }}</th><th>{{ __('Tags') }}</th><th>{{ __('Group') }}</th><th>{{ __('Confirmed') }}</th><th></th></tr>
                @else
                    <tr><th></th><th>{{ __('Date') }}</th><th>{{ __('Counterparty · communication') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('State') }}</th><th>{{ __('Category') }}</th><th>{{ __('Tags') }}</th><th>{{ __('Group') }}</th><th></th></tr>
                @endif
            </thead>
            <tbody id="lg-tbody">
            @forelse($transactions as $tx)
                @include($reviewed ? 'admin.ledger._row_reviewed' : 'admin.ledger._row', ['tx' => $tx])
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">{{ $reviewed ? __('Nothing confirmed yet.') : __('Nothing to review — import a statement to get started.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div id="lg-bulk" class="card-footer bg-body-secondary d-flex justify-content-between align-items-center flex-wrap gap-2">
        @unless($reviewed)
            <button type="submit" form="lg-bulk-form" class="btn btn-sm btn-success">{{ __('Confirm the selected lines (green only)') }}</button>
        @else
            <span></span>
        @endunless
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

@push('scripts')
<script>
(function () {
    function patchStateCounts(counts) {
        document.querySelectorAll('[data-state-pill]').forEach(function (el) {
            var key = el.getAttribute('data-state-pill');
            if (counts[key] !== undefined) { el.textContent = counts[key]; }
        });
    }

    function removeRow(id) {
        var row = document.querySelector('[data-ledger-row][data-ledger-id="' + id + '"]');
        if (row) { row.remove(); }
    }

    document.addEventListener('submit', function (e) {
        var form = e.target.closest('[data-ledger-ajax]');
        if (! form) { return; }
        e.preventDefault();

        var row = form.closest('[data-ledger-row]');
        var body = new FormData(form);

        // Confirming folds in whatever's currently showing in this row's group
        // box — a suggestion or a typed name the user never separately clicked
        // "Assign" on looks already "set" there, so Confirm honours it instead
        // of silently dropping it.
        if (form.hasAttribute('data-ledger-confirm') && row) {
            var groupInput = row.querySelector('input[name="new_name"]');
            if (groupInput && groupInput.value.trim() !== '') {
                body.append('new_name', groupInput.value.trim());
                var kindSelect = row.querySelector('select[name="new_kind"]');
                if (kindSelect) { body.append('new_kind', kindSelect.value); }
            }
        }

        fetch(form.getAttribute('action'), {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: body,
        })
            .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
            .then(function ({ ok, data }) {
                if (! ok || ! data.ok) {
                    window.showToast((data && data.message) || '{{ __('Something went wrong.') }}', 'danger');
                    return;
                }

                if (form.hasAttribute('data-ledger-bulk')) {
                    (data.removedIds || []).forEach(removeRow);
                } else if (data.removed) {
                    removeRow(data.id);
                } else if (data.html && row) {
                    var wrapper = document.createElement('tbody');
                    wrapper.innerHTML = data.html.trim();
                    row.replaceWith(wrapper.firstElementChild);
                }

                if (data.stateCounts) { patchStateCounts(data.stateCounts); }
                if (data.message) { window.showToast(data.message, data.success === false ? 'warning' : 'success'); }
            })
            .catch(function () {
                window.showToast('{{ __('Something went wrong.') }}', 'danger');
            });
    });
})();
</script>
@endpush
</x-admin-layout>
