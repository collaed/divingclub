<x-layout :title="__('Bank Reconciliation')">
    <h4 class="mb-4">{{ __('Bank Reconciliation') }}</h4>

    <div class="row mb-4">
        <div class="col-md-4"><div class="card dc-card text-center p-3"><h5>{{ $summary['unmatched'] }}</h5><small class="text-muted">{{ __('Unmatched') }}</small></div></div>
        <div class="col-md-4"><div class="card dc-card text-center p-3"><h5 class="text-warning">{{ $summary['suggested'] }}</h5><small class="text-muted">{{ __('Matched (pending confirm)') }}</small></div></div>
        <div class="col-md-4"><div class="card dc-card text-center p-3"><h5 class="text-success">{{ $summary['confirmed'] }}</h5><small class="text-muted">{{ __('Confirmed') }}</small></div></div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card dc-card">
                <div class="card-header">{{ __('Import Bank Statement') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.payments.import-statement') }}">
                        @csrf
                        <p class="small text-muted">{{ __('Paste bank statement (one line per transaction: date;amount;communication;counterparty)') }}</p>
                        <textarea name="statement" class="form-control mb-2" rows="5" placeholder="17/03/2026;153.50;CLUB-2026-3-DUPONT MARIE;Marie Dupont"></textarea>
                        <button class="btn btn-primary btn-sm">{{ __('Import') }}</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <form method="POST" action="{{ route('admin.payments.suggest-matches') }}">
                @csrf
                <button class="btn btn-warning w-100 mb-2">{{ __('Suggest Matches') }}</button>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Communication') }}</th><th>{{ __('Match') }}</th><th>{{ __('Score') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($transactions as $tx)
                <tr class="{{ $tx->status === 'confirmed' ? 'table-success' : ($tx->status === 'suggested' ? 'table-warning' : '') }}">
                    <td>{{ $tx->transaction_date->format('d/m/Y') }}</td>
                    <td>€{{ number_format($tx->amount, 2) }}</td>
                    <td class="small">{{ Str::limit($tx->communication, 40) }}</td>
                    <td class="small">{{ $tx->matchedPayment?->user?->name }}</td>
                    <td>{{ $tx->match_score ? $tx->match_score . '%' : '' }}</td>
                    <td><span class="badge bg-{{ $tx->status === 'confirmed' ? 'success' : ($tx->status === 'suggested' ? 'warning text-dark' : 'secondary') }}">{{ ucfirst($tx->status) }}</span></td>
                    <td>
                        @if($tx->status === 'suggested')
                            <form method="POST" action="{{ route('admin.payments.confirm-match', $tx) }}" class="d-inline">@csrf <button class="btn btn-sm btn-success">✓</button></form>
                        @endif
                        @if($tx->status !== 'confirmed' && $tx->status !== 'ignored')
                            <form method="POST" action="{{ route('admin.payments.ignore', $tx) }}" class="d-inline">@csrf <button class="btn btn-sm btn-outline-secondary">✕</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $transactions->links() }}
</x-layout>
