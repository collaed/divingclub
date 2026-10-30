<x-admin-layout :title="__('Transaction details')">
    @php $fees = app(\App\Services\FeeCalculationService::class); $pe = $transaction->matchedPayment; @endphp

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h4 class="mb-0">{{ __('Transaction details') }}</h4>
        <a href="{{ route('admin.payments.reconciliation') }}" class="small">{{ __('Back to reconciliation') }}</a>
    </div>

    <div class="card dc-card mb-4">
        <div class="card-header">{{ __('As received from the bank') }}</div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('Date') }}</dt>
                <dd class="col-sm-9">{{ $transaction->transaction_date->format('d/m/Y') }}</dd>
                <dt class="col-sm-3">{{ __('Amount') }}</dt>
                <dd class="col-sm-9">€{{ number_format((float) $transaction->amount, 2, ',', ' ') }}</dd>
                <dt class="col-sm-3">{{ __('Communication') }}</dt>
                <dd class="col-sm-9">{{ $transaction->communication ?: '—' }}</dd>
                <dt class="col-sm-3">{{ __('Counterparty') }}</dt>
                <dd class="col-sm-9">{{ $transaction->counterparty ?: '—' }}</dd>
                <dt class="col-sm-3">{{ __('Statement ref.') }}</dt>
                <dd class="col-sm-9">{{ $transaction->statement_ref ?: '—' }}</dd>
                <dt class="col-sm-3">{{ __('Status') }}</dt>
                <dd class="col-sm-9"><span class="badge bg-{{ $transaction->status === 'confirmed' ? 'success' : ($transaction->status === 'suggested' ? 'warning text-dark' : 'secondary') }}">{{ ucfirst($transaction->status) }}</span></dd>
                @if($transaction->match_score || $transaction->match_reason)
                    <dt class="col-sm-3">{{ __('Match') }}</dt>
                    <dd class="col-sm-9">
                        @if($transaction->match_score){{ $transaction->match_score }}%@endif
                        @if($transaction->match_reason) <span class="text-muted">— {{ $transaction->match_reason }}</span>@endif
                    </dd>
                @endif
                @if($transaction->status === 'confirmed')
                    <dt class="col-sm-3">{{ __('Confirmed by') }}</dt>
                    <dd class="col-sm-9">{{ $transaction->confirmedByUser?->name }}</dd>
                @endif
            </dl>
        </div>
    </div>

    <div class="card dc-card mb-4">
        <div class="card-header">{{ __('Currently linked to') }}</div>
        <div class="card-body">
            @if(! $pe)
                <p class="text-muted mb-0">{{ __('Nothing linked yet — search below.') }}</p>
            @else
                <p>
                    <strong>{{ $pe->user?->name }}</strong>
                    — {{ $pe->type === 'membership' ? __('Membership').' '.$pe->season_year : ($pe->type === 'event' ? __('Event').($pe->event ? ' — '.$pe->event->title : '') : ucfirst($pe->type)) }}
                    <span class="text-muted">(€{{ number_format((float) $pe->amount_paid, 2, ',', ' ') }} / €{{ number_format((float) $pe->amount_due, 2, ',', ' ') }})</span>
                </p>
                @if($pe->type === 'membership')
                    <table class="table table-sm table-borderless mb-0" style="max-width:24rem">
                        @foreach($fees->labelComponents($pe->components, (float) $pe->amount_due) as $line)
                            <tr class="{{ $line['muted'] ?? false ? 'text-muted' : '' }}">
                                <td class="py-0 {{ $line['bold'] ?? false ? 'fw-bold' : '' }}">{{ $line['label'] }}</td>
                                <td class="py-0 text-end {{ $line['bold'] ?? false ? 'fw-bold' : '' }}" style="width:6rem">€{{ number_format($line['amount'], 2, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </table>
                @elseif($pe->type === 'event')
                    <table class="table table-sm table-borderless mb-0" style="max-width:24rem">
                        @foreach($pe->components as $line)
                            <tr>
                                <td class="py-0">{{ $line['label'] }}</td>
                                <td class="py-0 text-end" style="width:6rem">€{{ number_format((float) $line['amount'], 2, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
                <div class="mt-3">
                    @if($transaction->status === 'suggested')
                        <form method="POST" action="{{ route('admin.payments.confirm-match', $transaction) }}" class="d-inline">@csrf <button class="btn btn-sm btn-success">{{ __('Confirm this match') }}</button></form>
                    @endif
                    @if($transaction->status !== 'confirmed' && $transaction->status !== 'ignored')
                        <form method="POST" action="{{ route('admin.payments.ignore', $transaction) }}" class="d-inline">@csrf <button class="btn btn-sm btn-outline-secondary">{{ __('Ignore') }}</button></form>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if($transaction->status !== 'confirmed')
        <div class="card dc-card">
            <div class="card-header">{{ __('Link to a different member') }}</div>
            <div class="card-body">
                <p class="small text-muted">{{ __('Shows pending dues, closest amount first. Search by member name or the text they put in the transfer.') }}</p>
                <form method="GET" action="{{ route('admin.payments.transactions.show', $transaction) }}" class="d-flex gap-2 mb-3">
                    <input type="text" name="q" class="form-control form-control-sm" style="max-width:20rem" placeholder="{{ __('Search name or communication…') }}" value="{{ $search }}">
                    <button class="btn btn-sm btn-outline-secondary">{{ __('Search') }}</button>
                </form>
                @if($candidates->isEmpty())
                    <p class="small text-muted">{{ __('No matching pending dues found.') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>{{ __('Member') }}</th><th>{{ __('For') }}</th><th>{{ __('Due') }}</th><th>{{ __('Communication') }}</th><th></th></tr></thead>
                            <tbody>
                            @foreach($candidates as $candidate)
                                <tr class="{{ $pe && $pe->id === $candidate->id ? 'table-active' : '' }}">
                                    <td class="small">{{ $candidate->user?->name }}</td>
                                    <td class="small">{{ $candidate->type === 'membership' ? __('Membership').' '.$candidate->season_year : ($candidate->type === 'event' ? __('Event') : ucfirst((string) $candidate->type)) }}</td>
                                    <td class="small">€{{ number_format((float) $candidate->amount_due, 2, ',', ' ') }}</td>
                                    <td class="small">{{ Str::limit($candidate->communication, 30) }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.payments.transactions.match', $transaction) }}">
                                            @csrf
                                            <input type="hidden" name="payment_id" value="{{ $candidate->id }}">
                                            <button class="btn btn-sm btn-outline-primary">{{ __('Link') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif
</x-admin-layout>
