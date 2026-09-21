<x-admin-layout :title="__('Membership renewals')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">{{ __('Membership renewals') }} {{ ((int) $year - 1).'-'.$year }}</h4>
            <small class="text-muted">{{ __('Members who have not paid this season yet. Honoraire members are marked as paid automatically on 1 October.') }}</small>
        </div>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <label for="season_year" class="form-label mb-0 small">{{ __('Season') }}</label>
            <input type="number" id="season_year" name="season_year" value="{{ $year }}" class="form-control form-control-sm" style="width:6rem" data-autosubmit>
        </form>
    </div>

    <div class="row mb-3">
        <div class="col-6 col-md-3"><div class="card dc-card text-center p-3"><h5 class="mb-0" data-renewal-count>{{ $rows->count() }}</h5><small class="text-muted">{{ __('Still to pay') }}</small></div></div>
        <div class="col-6 col-md-3"><div class="card dc-card text-center p-3"><h5 class="mb-0">€{{ number_format($total, 2) }}</h5><small class="text-muted">{{ __('Expected in total') }}</small></div></div>
    </div>

    <div class="card dc-card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="table-renewals">
                <thead>
                    <tr>
                        <th>{{ __('Member') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Last paid season') }}</th>
                        <th class="text-end">{{ __('Expected') }}</th>
                        <th>{{ __('Received') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    @php $u = $row['user']; $p = $row['proposal']; @endphp
                    <tr data-renewal-row data-url="{{ route('admin.payments.renewals.received', $u) }}" data-override-url="{{ route('admin.payments.renewals.override', $u) }}" data-year="{{ $year }}">
                        <td>
                            <a href="{{ route('admin.profile.show', $u) }}">{{ $u->detail?->last_name }} {{ $u->detail?->first_name }}</a>
                        </td>
                        <td>{{ $u->status?->name ?? '—' }}</td>
                        <td>{{ $row['lastPaid'] ?? '—' }}</td>
                        <td class="text-end">
                            <strong>€{{ number_format($p['amount'], 2) }}</strong>
                            <div class="small text-muted">
                                @if($p['source'] === 'committed') <span class="badge bg-info text-dark">{{ __('They committed to this amount') }}</span>
                                @else {{ __('Same options as last season') }} @endif
                                @if($p['insurance']) · {{ $p['insurance'] }} @endif
                            </div>
                        </td>
                        <td style="min-width:20rem">
                            <div class="d-flex gap-2 flex-wrap" data-renewal-actions>
                                <button type="button" class="btn btn-sm btn-success" data-renewal-received>{{ __('Received €:amount', ['amount' => number_format($p['amount'], 2)]) }}</button>
                                <div class="input-group input-group-sm" style="width:11rem">
                                    <span class="input-group-text">€</span>
                                    <input type="number" step="0.01" min="0" class="form-control" data-renewal-amount placeholder="{{ __('Other amount') }}" aria-label="{{ __('Amount received') }}">
                                    <button type="button" class="btn btn-outline-primary" data-renewal-check>{{ __('Check') }}</button>
                                </div>
                            </div>
                            <details class="mt-1" data-renewal-override-box>
                                <summary class="small text-muted">{{ __('Paid another way (cash, other)…') }}</summary>
                                <div class="d-flex gap-2 flex-wrap mt-1 align-items-center">
                                    <select class="form-select form-select-sm" style="width:auto" data-renewal-method aria-label="{{ __('How it was paid') }}">
                                        <option value="cash">{{ __('Cash') }}</option>
                                        <option value="transfer">{{ __('Transfer (not reconciled)') }}</option>
                                        <option value="other">{{ __('Other') }}</option>
                                    </select>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" style="width:7rem" data-renewal-override-amount placeholder="{{ number_format($p['amount'], 2, '.', '') }}" aria-label="{{ __('Amount received') }}">
                                    <input type="text" maxlength="255" class="form-control form-control-sm" style="width:12rem" data-renewal-note placeholder="{{ __('Note (optional)') }}" aria-label="{{ __('Note') }}">
                                    <button type="button" class="btn btn-sm btn-outline-success" data-renewal-override>{{ __('Mark as paid') }}</button>
                                </div>
                            </details>
                            <div class="small mt-1" data-renewal-message role="status" aria-live="polite"></div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">{{ __('Everyone has paid this season.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
