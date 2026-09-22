<x-admin-layout :title="__('Ledger operations')">
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">{{ __('Operations') }}</h4>
        <small class="text-muted">{{ __('Groups of transactions that belong together: a closed loop nets to zero, a trip settles once, a cost centre never closes.') }}</small>
    </div>
    <a href="{{ route('admin.ledger.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Back to the ledger') }}</a>
</div>

<div class="row g-3">
@forelse($operations as $op)
    @php $net = (float) $op->transactions->sum('amount'); @endphp
    <div class="col-lg-6"><div class="card dc-card h-100"><div class="card-body">
        <div class="d-flex justify-content-between">
            <h6 class="mb-0">{{ $op->name }}</h6>
            <span class="badge bg-secondary text-uppercase">{{ $op->kind }}</span>
        </div>
        <div class="small text-muted mb-2">{{ $op->transactions_count }} {{ __('line(s)') }} @if($op->budget_amount) · {{ __('budget') }} €{{ number_format((float) $op->budget_amount, 2) }} @endif</div>
        <div class="d-flex justify-content-between fw-semibold">
            <span>{{ __('Net') }}</span>
            <span class="{{ abs($net) < 1 ? 'text-success' : ($net >= 0 ? 'text-success' : 'text-danger') }}">{{ number_format($net, 2, ',', ' ') }}</span>
        </div>
    </div></div></div>
@empty
    <p class="text-muted">{{ __('No operations yet — assign a transaction to one from the ledger inbox.') }}</p>
@endforelse
</div>
</x-admin-layout>
