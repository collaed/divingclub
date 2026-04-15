<x-admin-layout :title="__('Financial Audit')">
    <h4 class="mb-3">📋 {{ __('Financial Audit — Réviseur aux Comptes') }}</h4>
    <p class="text-muted small">{{ __('Read-only view of all financial flows. Payments expected, amounts received, and bank statement reconciliation.') }}</p>

    {{-- Summary --}}
    <div class="row g-3 mb-4">
        <div class="col-md-2"><div class="card dc-card text-center p-3"><div class="fs-4 fw-bold">€{{ number_format($summary['total_due'], 2) }}</div><small class="text-muted">{{ __('Total Due') }}</small></div></div>
        <div class="col-md-2"><div class="card dc-card text-center p-3"><div class="fs-4 fw-bold text-success">€{{ number_format($summary['total_paid'], 2) }}</div><small class="text-muted">{{ __('Collected') }}</small></div></div>
        <div class="col-md-2"><div class="card dc-card text-center p-3"><div class="fs-4 fw-bold text-danger">€{{ number_format($summary['outstanding'], 2) }}</div><small class="text-muted">{{ __('Outstanding') }}</small></div></div>
        <div class="col-md-2"><div class="card dc-card text-center p-3"><div class="fs-4 fw-bold">{{ $summary['paid_count'] }}/{{ $summary['payment_count'] }}</div><small class="text-muted">{{ __('Paid/Total') }}</small></div></div>
        <div class="col-md-2"><div class="card dc-card text-center p-3"><div class="fs-4 fw-bold text-success">{{ $summary['matched_tx'] }}</div><small class="text-muted">{{ __('Matched TX') }}</small></div></div>
        <div class="col-md-2"><div class="card dc-card text-center p-3"><div class="fs-4 fw-bold text-warning">{{ $summary['unmatched_tx'] }}</div><small class="text-muted">{{ __('Unmatched TX') }}</small></div></div>
    </div>

    {{-- Payments Expected --}}
    <div class="card dc-card mb-4">
        <div class="card-header fw-bold">💰 {{ __('Payments Expected') }}</div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>{{ __('Member') }}</th><th>{{ __('Type') }}</th><th>{{ __('Event') }}</th><th>{{ __('Due') }}</th><th>{{ __('Paid') }}</th><th>{{ __('Communication') }}</th><th>{{ __('Status') }}</th><th>{{ __('Date') }}</th></tr></thead>
                <tbody>
                    @foreach($payments as $p)
                        <tr>
                            <td>{{ $p->user?->detail?->first_name }} {{ $p->user?->detail?->last_name }}</td>
                            <td><span class="badge bg-secondary">{{ $p->type }}</span></td>
                            <td>{{ $p->event?->title ?? '—' }}</td>
                            <td class="text-end">€{{ number_format($p->amount_due, 2) }}</td>
                            <td class="text-end">€{{ number_format($p->amount_paid, 2) }}</td>
                            <td class="small text-muted">{{ $p->communication }}</td>
                            <td>
                                @if($p->status === 'paid')
                                    <span class="badge bg-success">{{ __('Paid') }}</span>
                                @else
                                    <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                                @endif
                            </td>
                            <td class="small">{{ $p->paid_at ?? $p->created_at?->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $payments->links() }}</div>
    </div>

    {{-- Bank Transactions --}}
    <div class="card dc-card">
        <div class="card-header fw-bold">🏦 {{ __('Bank Transactions') }}</div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Counterparty') }}</th><th>{{ __('Communication') }}</th><th>{{ __('Matched To') }}</th><th>{{ __('Score') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @foreach($transactions as $tx)
                        <tr>
                            <td>{{ $tx->transaction_date }}</td>
                            <td class="text-end fw-bold {{ $tx->amount >= 0 ? 'text-success' : 'text-danger' }}">€{{ number_format($tx->amount, 2) }}</td>
                            <td>{{ $tx->counterparty }}</td>
                            <td class="small text-muted">{{ Str::limit($tx->communication, 40) }}</td>
                            <td>
                                @if($tx->matchedPayment)
                                    {{ $tx->matchedPayment->user?->detail?->first_name }} {{ $tx->matchedPayment->user?->detail?->last_name }}
                                    <span class="small text-muted">({{ $tx->matchedPayment->communication }})</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $tx->match_score ? $tx->match_score.'%' : '—' }}</td>
                            <td>
                                @if($tx->status === 'matched')
                                    <span class="badge bg-success">{{ __('Matched') }}</span>
                                @elseif($tx->status === 'confirmed')
                                    <span class="badge bg-primary">{{ __('Confirmed') }}</span>
                                @else
                                    <span class="badge bg-warning text-dark">{{ __('Unmatched') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($transactions->hasPages())
            <div class="card-footer">{{ $transactions->links() }}</div>
        @endif
    </div>
</x-admin-layout>
