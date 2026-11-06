<x-admin-layout :title="__('Licence renewals')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">{{ __('Licence renewals pending') }}</h4>
            <small class="text-muted">{{ __('Members whose FFESSM and/or FLASSA licence is marked "request pending". Select rows, then clear the federation(s) once confirmed — no need to save one at a time.') }}</small>
        </div>
    </div>

    <div class="card dc-card">
        <div class="d-flex align-items-center gap-2 p-2 border-bottom flex-wrap bg-white sticky-top" style="top:0;z-index:5" data-licence-toolbar>
            <div class="form-check form-check-inline mb-0">
                <input type="checkbox" class="form-check-input" data-licence-select-all="FFESSM" id="licenceSelectAllFfessm">
                <label class="form-check-label small" for="licenceSelectAllFfessm">{{ __('All') }}</label>
            </div>
            <button type="button" class="btn btn-sm btn-success" data-licence-clear="FFESSM"
                data-clear-url="{{ route('admin.payments.licence-renewals.clear') }}"
                data-saving-label="{{ __('Saving…') }}"
                data-error-label="{{ __('Network error — nothing was cleared.') }}"
                data-done-label="{{ __(':count done for :federation') }}"
                disabled>@icon('✅') {{ __('FFESSM done') }}</button>
            <div class="form-check form-check-inline mb-0 ms-3">
                <input type="checkbox" class="form-check-input" data-licence-select-all="FLASSA" id="licenceSelectAllFlassa">
                <label class="form-check-label small" for="licenceSelectAllFlassa">{{ __('All') }}</label>
            </div>
            <button type="button" class="btn btn-sm btn-success" data-licence-clear="FLASSA"
                data-clear-url="{{ route('admin.payments.licence-renewals.clear') }}"
                data-saving-label="{{ __('Saving…') }}"
                data-error-label="{{ __('Network error — nothing was cleared.') }}"
                data-done-label="{{ __(':count done for :federation') }}"
                disabled>@icon('✅') {{ __('FLASSA done') }}</button>
            <span class="text-muted small ms-auto" data-licence-selected-count>{{ __('No rows selected') }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="table-licence-renewals">
                <thead>
                    <tr>
                        <th>{{ __('Member') }}</th>
                        <th>{{ __('Age') }}</th>
                        <th>{{ __('Last medical cert') }}</th>
                        <th class="text-end">{{ __('Amount due') }} {{ $year }}</th>
                        <th>{{ __('Insurance') }}</th>
                        <th>{{ __('Pending — select to clear') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    @php
                        $u = $row['user'];
                        $cert = $row['cert'];
                        $reviewComment = $cert['cert']?->review_comment;
                        $validatedWithComment = in_array($cert['status'], ['compliant', 'expiring'], true) && $reviewComment;
                    @endphp
                    <tr data-licence-row data-user-id="{{ $u->id }}">
                        <td><a href="{{ route('admin.profile.show', $u) }}">{{ $u->detail?->last_name }} {{ $u->detail?->first_name }}</a></td>
                        <td>{{ $row['age'] ?? '—' }}</td>
                        <td>
                            @if($cert['cert'])
                                {{ $cert['cert']->date_established?->format('d/m/Y') ?? $cert['cert']->created_at->format('d/m/Y') }}
                                @if($validatedWithComment)
                                    <span class="badge bg-warning text-dark ms-1">{{ __('Validated (comment)') }}</span>
                                    <div class="small text-muted">{{ $reviewComment }}</div>
                                @else
                                    <span class="badge bg-{{ $cert['badge'] }} ms-1">{{ __($cert['label']) }}</span>
                                @endif
                            @else
                                <span class="badge bg-danger">{{ __('No Certificate') }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($row['amountDue'] !== null)
                                €{{ number_format((float) $row['amountDue'], 2) }}
                                <span class="text-muted small">({{ __($row['paymentStatus'] ?? '') }})</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $row['insurance'] ?? '—' }}</td>
                        <td data-licence-pending-cell>
                            @if($row['pendingFfessm'])
                                <label class="badge bg-warning text-dark" data-pending-fed="FFESSM" style="cursor:pointer">
                                    <input type="checkbox" class="form-check-input me-1" style="vertical-align:text-bottom" data-licence-select="FFESSM" value="{{ $u->id }}">FFESSM
                                </label>
                            @endif
                            @if($row['pendingFlassa'])
                                <label class="badge bg-warning text-dark ms-1" data-pending-fed="FLASSA" style="cursor:pointer">
                                    <input type="checkbox" class="form-check-input me-1" style="vertical-align:text-bottom" data-licence-select="FLASSA" value="{{ $u->id }}">FLASSA
                                </label>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No licence renewal is pending.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

</x-admin-layout>
