<x-layout :title="__('Payments & Fees')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">{{ __('Payments & Fees') }}</h4>
        <a href="{{ route('admin.payments.components') }}" class="btn btn-sm btn-outline-primary">{{ __('Fee Components') }}</a>
    </div>

    <div class="row mb-4">
        <div class="col-md-3"><div class="card dc-card text-center p-3"><h5 class="text-success">€{{ number_format($payments->where('status', 'paid')->sum('amount_paid'), 2) }}</h5><small class="text-muted">{{ __('Collected') }}</small></div></div>
        <div class="col-md-3"><div class="card dc-card text-center p-3"><h5 class="text-warning">€{{ number_format($payments->where('status', 'pending')->sum('amount_due'), 2) }}</h5><small class="text-muted">{{ __('Outstanding') }}</small></div></div>
        <div class="col-md-3"><div class="card dc-card text-center p-3"><h5>{{ $payments->where('status', 'paid')->count() }}</h5><small class="text-muted">{{ __('Paid') }}</small></div></div>
        <div class="col-md-3"><div class="card dc-card text-center p-3"><h5>{{ $payments->where('status', 'pending')->count() }}</h5><small class="text-muted">{{ __('Pending') }}</small></div></div>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('All Statuses') }}</option>
                @foreach(['pending','paid','partial','cancelled'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead><tr><th>{{ __('Member') }}</th><th>{{ __('Type') }}</th><th>{{ __('Due') }}</th><th>{{ __('Paid') }}</th><th>{{ __('Communication') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @foreach($payments as $p)
                <tr>
                    <td>{{ $p->user?->name }}</td>
                    <td><span class="badge bg-secondary">{{ $p->type }}</span></td>
                    <td>€{{ number_format($p->amount_due, 2) }}</td>
                    <td>€{{ number_format($p->amount_paid, 2) }}</td>
                    <td><code class="small">{{ $p->communication }}</code></td>
                    <td><span class="badge bg-{{ $p->status === 'paid' ? 'success' : ($p->status === 'pending' ? 'warning text-dark' : 'secondary') }}">{{ ucfirst($p->status) }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $payments->links() }}

    <div class="mt-4">
        <a href="{{ route('admin.payments.reconciliation') }}" class="btn btn-primary">{{ __('Bank Reconciliation') }}</a>
    </div>
</x-layout>
