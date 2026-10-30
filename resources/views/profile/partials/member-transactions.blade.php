{{-- Bank-reconciliation view of this member's payments (Payments → Membership
     renewals → Reconciliation, scoped to one member) — bureau-visible, not
     bureau_master-only like the Ledger "Cotisation bank payments" block below. --}}
@php $fees = app(\App\Services\FeeCalculationService::class); @endphp

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
    <h6 class="mb-0">{{ __('Identified payments') }}</h6>
    <a href="{{ route('admin.payments.reconciliation') }}" class="small">{{ __('Open reconciliation') }}</a>
</div>
@if($identifiedTransactions->isEmpty())
    <p class="small text-muted">{{ __('No confirmed payments linked to this member yet.') }}</p>
@else
    <div class="table-responsive mb-4">
        <table class="table table-sm">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Amount') }}</th><th>{{ __('For') }}</th></tr></thead>
            <tbody>
            @foreach($identifiedTransactions as $tx)
                @php $pe = $tx->matchedPayment; @endphp
                <tr>
                    <td class="small">{{ $tx->transaction_date->format('d/m/Y') }}</td>
                    <td class="small">€{{ number_format((float) $tx->amount, 2, ',', ' ') }}</td>
                    <td class="small">
                        @if($pe?->type === 'membership')
                            {{ __('Membership') }} {{ $pe->season_year }}
                            <table class="table table-sm table-borderless mb-0 mt-1">
                                @foreach($fees->labelComponents($pe->components, (float) $pe->amount_due) as $line)
                                    <tr class="{{ $line['muted'] ?? false ? 'text-muted' : '' }}">
                                        <td class="py-0 {{ $line['bold'] ?? false ? 'fw-bold' : '' }}">{{ $line['label'] }}</td>
                                        <td class="py-0 text-end {{ $line['bold'] ?? false ? 'fw-bold' : '' }}" style="width:6rem">€{{ number_format($line['amount'], 2, ',', ' ') }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @elseif($pe?->type === 'event')
                            {{ __('Event') }} @if($pe->event)— {{ $pe->event->title }}@endif
                            <table class="table table-sm table-borderless mb-0 mt-1">
                                @foreach($pe->components as $line)
                                    <tr>
                                        <td class="py-0">{{ $line['label'] }}</td>
                                        <td class="py-0 text-end" style="width:6rem">€{{ number_format((float) $line['amount'], 2, ',', ' ') }}</td>
                                    </tr>
                                @endforeach
                                <tr class="fw-bold">
                                    <td class="py-0">{{ __('Total') }}</td>
                                    <td class="py-0 text-end">€{{ number_format((float) $pe->amount_due, 2, ',', ' ') }}</td>
                                </tr>
                            </table>
                        @else
                            {{ $pe?->type ? ucfirst($pe->type) : '—' }}
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
    <h6 class="mb-0">{{ __('Suspected matches') }}</h6>
</div>
@if($suspectedTransactions->isEmpty())
    <p class="small text-muted">{{ __('No suggested matches for this member.') }}</p>
@else
    <div class="table-responsive mb-4">
        <table class="table table-sm">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Communication') }}</th><th>{{ __('Score') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($suspectedTransactions as $tx)
                <tr>
                    <td class="small">{{ $tx->transaction_date->format('d/m/Y') }}</td>
                    <td class="small">€{{ number_format((float) $tx->amount, 2, ',', ' ') }}</td>
                    <td class="small"><a href="{{ route('admin.payments.transactions.show', $tx) }}">{{ Str::limit($tx->communication, 50) }}</a></td>
                    <td class="small">
                        <span @if($tx->match_reason) title="{{ $tx->match_reason }}" style="cursor:help" @endif>{{ $tx->match_score }}%{{ $tx->match_reason ? ' 🤖' : '' }}</span>
                    </td>
                    <td class="text-nowrap">
                        <form method="POST" action="{{ route('admin.payments.confirm-match', $tx) }}" class="d-inline">@csrf <button class="btn btn-sm btn-success">✓</button></form>
                        <form method="POST" action="{{ route('admin.payments.ignore', $tx) }}" class="d-inline">@csrf <button class="btn btn-sm btn-outline-secondary">✕</button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
