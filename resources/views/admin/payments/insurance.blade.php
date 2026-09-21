<x-admin-layout :title="__('Insurance to register')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">{{ __('Insurance to register') }} {{ ((int) $year - 1).'-'.$year }}</h4>
            <small class="text-muted">{{ __('Paid memberships with an insurance option, in the order they were paid. Tick each one once it is registered with the insurer; next season starts from the same option.') }}</small>
        </div>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <label for="season_year" class="form-label mb-0 small">{{ __('Season') }}</label>
            <input type="number" id="season_year" name="season_year" value="{{ $year }}" class="form-control form-control-sm" style="width:6rem" data-autosubmit>
            <div class="form-check ms-2">
                <input type="checkbox" name="all" value="1" id="allToggle" class="form-check-input" data-autosubmit {{ $includeRegistered ? 'checked' : '' }}>
                <label class="form-check-label small" for="allToggle">{{ __('Include already registered') }}</label>
            </div>
        </form>
    </div>

    @if($perTier->isNotEmpty())
        <p class="mb-3">
            @foreach($perTier as $name => $count)
                <span class="badge bg-light text-dark border me-1">{{ $name }} × {{ $count }}</span>
            @endforeach
        </p>
    @endif

    <div class="card dc-card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="table-insurance">
                <thead>
                    <tr>
                        <th>{{ __('Paid on') }}</th>
                        <th>{{ __('Member') }}</th>
                        <th>{{ __('Insurance') }}</th>
                        <th class="text-end">{{ __('Amount') }}</th>
                        <th>{{ __('How') }}</th>
                        <th>{{ __('Registered') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($queue as $row)
                    @php $p = $row['payment']; $u = $p->user; @endphp
                    <tr data-insurance-row data-url="{{ route('admin.payments.insurance.registered', $p) }}">
                        <td>{{ $p->paid_at?->format('d/m/Y') }}</td>
                        <td><a href="{{ route('admin.profile.show', $u) }}">{{ $u?->detail?->last_name }} {{ $u?->detail?->first_name }}</a></td>
                        <td>{{ $row['tier']->name }} <span class="text-muted small">€{{ number_format((float) $row['tier']->amount, 2) }}</span></td>
                        <td class="text-end">€{{ number_format((float) $p->amount_paid, 2) }}</td>
                        <td>{{ $p->payment_method ? ucfirst($p->payment_method) : __('Bank') }}@if($p->note) <span class="text-muted small">— {{ $p->note }}</span>@endif</td>
                        <td>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" data-insurance-toggle id="ins_{{ $p->id }}" {{ $p->insurance_registered_at ? 'checked' : '' }}>
                                <label class="form-check-label small" for="ins_{{ $p->id }}" data-insurance-label>{{ $p->insurance_registered_at ? $p->insurance_registered_at->format('d/m/Y') : __('Registered') }}</label>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Nothing to register.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
