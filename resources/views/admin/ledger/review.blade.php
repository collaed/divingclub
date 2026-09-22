<x-admin-layout :title="__('Review by tag / group')">
<style>
    .lg-cloud-item { display:inline-flex; align-items:center; gap:.35rem; padding:.25rem .65rem; border-radius:999px; font-size:.85rem; border:1px solid var(--bs-border-color); background:var(--bs-tertiary-bg); cursor:pointer; user-select:none; }
    .lg-cloud-item input { margin:0; }
    .lg-cloud-item.lg-picked { border-color:var(--bs-primary); background:var(--bs-primary-bg-subtle); font-weight:600; }
    .lg-cloud-item small { opacity:.65; }
    .lg-tag-in  { border-color:var(--bs-success-border-subtle); }
    .lg-tag-out { border-color:var(--bs-danger-border-subtle); }
    .lg-num { font-variant-numeric: tabular-nums; text-align:right; white-space:nowrap; }
    .lg-in { color:#146c43; } .lg-out { color:#b02a37; }
    .lg-chip { display:inline-block; padding:.05rem .45rem; border-radius:.35rem; font-size:.72rem; background:var(--bs-tertiary-bg); border:1px solid var(--bs-border-color); }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">{{ __('Review by tag / group') }}</h4>
        <small class="text-muted">{{ __('Pick any number of tags or groups below to see every matching line and whether they balance out — a line matches if it carries any picked tag, or belongs to any picked group.') }}</small>
    </div>
    <a href="{{ route('admin.ledger.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Back to the ledger') }}</a>
</div>

<form method="GET" action="{{ route('admin.ledger.review') }}" id="lg-review-form">
    <div class="card dc-card mb-3">
        <div class="card-header">{{ __('Tags') }}</div>
        <div class="card-body d-flex flex-wrap gap-2">
            @forelse($tags as $tag)
                @php $dirClass = $tag->direction === 'in' ? 'lg-tag-in' : ($tag->direction === 'out' ? 'lg-tag-out' : ''); @endphp
                <label class="lg-cloud-item {{ $dirClass }} {{ in_array($tag->id, $selectedTagIds) ? 'lg-picked' : '' }}">
                    <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="form-check-input" onchange="document.getElementById('lg-review-form').submit()" {{ in_array($tag->id, $selectedTagIds) ? 'checked' : '' }}>
                    #{{ $tag->label }} <small>· {{ $tag->transactions_count }}</small>
                </label>
            @empty
                <span class="text-muted small">{{ __('No tags yet.') }}</span>
            @endforelse
        </div>
    </div>

    <div class="card dc-card mb-3">
        <div class="card-header">{{ __('Groups') }}</div>
        <div class="card-body d-flex flex-wrap gap-2">
            @forelse($operations as $op)
                <label class="lg-cloud-item {{ in_array($op->id, $selectedOperationIds) ? 'lg-picked' : '' }}">
                    <input type="checkbox" name="operations[]" value="{{ $op->id }}" class="form-check-input" onchange="document.getElementById('lg-review-form').submit()" {{ in_array($op->id, $selectedOperationIds) ? 'checked' : '' }}>
                    {{ $op->name }} <small>· {{ $op->transactions_count }}</small>
                </label>
            @empty
                <span class="text-muted small">{{ __('No groups yet.') }}</span>
            @endforelse
        </div>
    </div>
</form>

@if($selectedTagIds || $selectedOperationIds)
    <div class="row g-2 mb-3">
        <div class="col-4"><div class="card dc-card text-center p-3"><div class="small text-muted">{{ __('In') }}</div><div class="h5 mb-0 lg-in">+{{ number_format($totalIn, 2, ',', ' ') }}</div></div></div>
        <div class="col-4"><div class="card dc-card text-center p-3"><div class="small text-muted">{{ __('Out') }}</div><div class="h5 mb-0 lg-out">{{ number_format($totalOut, 2, ',', ' ') }}</div></div></div>
        <div class="col-4"><div class="card dc-card text-center p-3"><div class="small text-muted">{{ __('Net') }}</div><div class="h5 mb-0 {{ abs($net) < 1 ? 'text-success' : ($net >= 0 ? 'lg-in' : 'lg-out') }}">{{ number_format($net, 2, ',', ' ') }}</div></div></div>
    </div>

    <div class="card dc-card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Counterparty') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Tags') }}</th><th>{{ __('Groups') }}</th><th>{{ __('Confirmed') }}</th></tr></thead>
                <tbody>
                @forelse($transactions as $tx)
                    <tr>
                        <td class="small">{{ $tx->transaction_date->format('d/m/Y') }}</td>
                        <td>{{ $tx->counterparty?->name ?? $tx->counterparty_name ?? __('Unknown') }}</td>
                        <td class="lg-num {{ $tx->amount >= 0 ? 'lg-in' : 'lg-out' }}">{{ $tx->amount >= 0 ? '+' : '−' }}{{ number_format(abs((float) $tx->amount), 2, ',', ' ') }}</td>
                        <td>@foreach($tx->tags as $t)<span class="lg-chip">#{{ $t->label }}</span> @endforeach</td>
                        <td>@foreach($tx->operations as $op)<span class="lg-chip">{{ $op->name }}</span> @endforeach</td>
                        <td>{{ $tx->confirmed_at ? '✓' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No lines match this selection.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-muted">{{ __(':count line(s).', ['count' => $transactions->count()]) }}</div>
    </div>
@else
    <p class="text-muted">{{ __('Pick at least one tag or group above.') }}</p>
@endif
</x-admin-layout>
