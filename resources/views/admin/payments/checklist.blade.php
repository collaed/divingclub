<x-admin-layout :title="__('Payment Action Checklist')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">{{ __('Payment Action Checklist') }} {{ ((int) $year - 1).'-'.$year }}</h4>
            <small class="text-muted">{{ __('Who paid, how much, and what the bureau still owes them in return: a medical cert, an FFESSM/FLASSA licence renewal, or registering their insurance.') }}</small>
        </div>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <label for="season_year" class="form-label mb-0 small">{{ __('Season') }}</label>
            <input type="number" id="season_year" name="season_year" value="{{ $year }}" class="form-control form-control-sm" style="width:6rem" data-autosubmit>
            <div class="form-check ms-2">
                <input type="checkbox" name="all" value="1" id="allToggle" class="form-check-input" data-autosubmit {{ $showResolved ? 'checked' : '' }}>
                <label class="form-check-label small" for="allToggle">{{ __('Include fully resolved') }}</label>
            </div>
        </form>
    </div>

    <div class="card dc-card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="table-checklist">
                <thead>
                    <tr>
                        <th>{{ __('Paid on') }}</th>
                        <th>{{ __('Member') }}</th>
                        <th class="text-end">{{ __('Amount') }}</th>
                        <th>{{ __('Medical cert') }}</th>
                        <th>{{ __('Licence renewal') }}</th>
                        <th>{{ __('Insurance') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    @php $p = $row['payment']; $u = $row['user']; $cert = $row['cert']; @endphp
                    <tr data-checklist-row data-insurance-row data-user-id="{{ $u->id }}" data-url="{{ route('admin.payments.insurance.registered', $p) }}" class="{{ $row['allDone'] ? 'table-success' : '' }}">
                        <td class="small">
                            {{ $p->paid_at?->format('d/m/Y') }}
                            <div class="text-muted">{{ $p->payment_method ? ucfirst($p->payment_method) : __('Bank') }}@if($p->bank_statement_ref) — {{ $p->bank_statement_ref }}@endif</div>
                        </td>
                        <td><a href="{{ route('admin.profile.show', $u) }}">{{ $u->detail?->last_name }} {{ $u->detail?->first_name }}</a></td>
                        <td class="text-end">€{{ number_format((float) $p->amount_paid, 2) }}</td>
                        <td>
                            <span class="badge bg-{{ $cert['badge'] }}">{{ __($cert['label']) }}</span>
                        </td>
                        <td data-checklist-licence-cell>
                            @if($row['pendingFfessm'])
                                <span class="badge bg-warning text-dark" data-checklist-fed="FFESSM">FFESSM</span>
                                <button type="button" class="btn btn-sm btn-outline-success py-0 px-1" data-checklist-clear-fed="FFESSM" data-user-id="{{ $u->id }}" data-clear-url="{{ route('admin.payments.licence-renewals.clear') }}" title="{{ __('Mark FFESSM renewal done') }}">✓</button>
                            @endif
                            @if($row['pendingFlassa'])
                                <span class="badge bg-warning text-dark ms-1" data-checklist-fed="FLASSA">FLASSA</span>
                                <button type="button" class="btn btn-sm btn-outline-success py-0 px-1" data-checklist-clear-fed="FLASSA" data-user-id="{{ $u->id }}" data-clear-url="{{ route('admin.payments.licence-renewals.clear') }}" title="{{ __('Mark FLASSA renewal done') }}">✓</button>
                            @endif
                            @if(! $row['pendingFfessm'] && ! $row['pendingFlassa'])
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            @if($row['insuranceTier'])
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" data-insurance-toggle id="ins_{{ $p->id }}" {{ $p->insurance_registered_at ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="ins_{{ $p->id }}" data-insurance-label>{{ $row['insuranceTier']->name }}{{ $p->insurance_registered_at ? ' — '.$p->insurance_registered_at->format('d/m/Y') : '' }}</label>
                                </div>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Nothing outstanding for this season.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
