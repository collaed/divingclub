<x-layout :title="__('Manage Settlement') . ' — ' . $event->title">
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('events.index') }}">{{ __('Calendar') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('events.show', $event) }}">{{ $event->title }}</a></li>
        <li class="breadcrumb-item active">{{ __('Manage Settlement') }}</li>
    </ol></nav>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>{{ __('Trip Settlement') }} — {{ $event->title }}</h4>
        <div>
            <a href="{{ route('events.settlement.breakdown', $event) }}" class="btn btn-outline-primary me-2" target="_blank">🖨️ {{ __('Print Breakdown') }}</a>
            <a href="{{ route('events.settlement.export', $event) }}" class="btn btn-outline-success me-2">📊 {{ __('Export Excel') }}</a>
            @if($event->settlement_status === 'open')
                <form action="{{ route('events.settlement.close', $event) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger" data-confirm="{{ __('Close the ledger? No further changes will be allowed.') }}">{{ __('Close Ledger') }}</button>
                </form>
            @else
                <span class="badge bg-secondary me-2">{{ __('Ledger Closed') }}</span>
                <form action="{{ route('events.settlement.reopen', $event) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-warning">{{ __('Reopen') }}</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Summary Cards (click to expand composition) --}}
    @php
        $globalReceipts = $event->tripReceipts()->where('status','approved')->where('category','general')->get();
        $transitReceipts = $event->tripReceipts()->where('status','approved')->where('category','transit')->get();
        $transitTotal = $settlement['transit_pool'] + $settlement['driver_bounties'];
    @endphp
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card dc-card text-center" role="button" data-bs-toggle="collapse" data-bs-target="#detail-global">
                <div class="card-body">
                    <h5>{{ number_format($settlement['global_pool'], 2) }} €</h5>
                    <small class="text-muted">{{ __('Global Pool') }}</small>
                </div>
            </div>
            <div class="collapse small mt-1" id="detail-global">
                <div class="card card-body py-2" style="font-size:0.8rem">
                    @foreach($globalReceipts as $r)
                        <div class="d-flex justify-content-between"><span>{{ $r->description }}</span><span>{{ number_format($r->approved_amount, 2) }} €</span></div>
                    @endforeach
                    @if($globalReceipts->isEmpty())<span class="text-muted">{{ __('No receipts') }}</span>@endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card dc-card text-center" role="button" data-bs-toggle="collapse" data-bs-target="#detail-transit">
                <div class="card-body">
                    <h5>{{ number_format($transitTotal, 2) }} €</h5>
                    <small class="text-muted">{{ __('Transit Pool') }} <span class="text-muted">({{ __('incl. bounties') }} {{ number_format($settlement['driver_bounties'], 2) }} €)</span></small>
                </div>
            </div>
            <div class="collapse small mt-1" id="detail-transit">
                <div class="card card-body py-2" style="font-size:0.8rem">
                    @foreach($transitReceipts as $r)
                        <div class="d-flex justify-content-between"><span>{{ $r->description }}@if($r->user) <em class="text-muted">({{ $r->user->detail?->first_name }})</em>@endif</span><span>{{ number_format($r->approved_amount, 2) }} €</span></div>
                    @endforeach
                    <div class="d-flex justify-content-between border-top mt-1 pt-1"><span>{{ __('Driver Bounties') }}</span><span>{{ number_format($settlement['driver_bounties'], 2) }} €</span></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card dc-card text-center" role="button" data-bs-toggle="collapse" data-bs-target="#detail-local">
                <div class="card-body">
                    <h5>{{ number_format($settlement['local_subsidy'], 2) }} €</h5>
                    <small class="text-muted">{{ __('Local Subsidy') }}</small>
                </div>
            </div>
            <div class="collapse small mt-1" id="detail-local">
                <div class="card card-body py-2" style="font-size:0.8rem">
                    <span class="text-muted">{{ __('Day rate') }}: {{ number_format($event->local_daily_charge ?? 0, 2) }} € × {{ __('days per non-van participant') }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Net Result for the Club --}}
    @php
        $totalPrepaid = collect($settlement['participants'])->sum('prepaid');
        $totalExpenses = $settlement['global_pool'] + $settlement['transit_pool'] + $settlement['driver_bounties'];
        $totalRefunds = collect($settlement['participants'])->where('cancelled', true)->sum('prepaid');
        $totalIndividualCharged = collect($settlement['participants'])->sum('individual_charges');
        $diveInvoice = $event->tripReceipts()->where('status', 'approved')->where('category', 'diving')->sum('approved_amount');
        $diveDelta = $totalIndividualCharged - $diveInvoice;
        $netResult = $totalPrepaid - $totalExpenses - $totalRefunds - $diveInvoice;
    @endphp
    <div class="alert {{ $netResult >= 0 ? 'alert-success' : 'alert-warning' }} mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>{{ __('Club Net Result') }}:</strong>
                {{ __('Collected') }}: {{ number_format($totalPrepaid, 2) }} €
                — {{ __('Accommodation') }}: {{ number_format($settlement['global_pool'], 2) }} €
                — {{ __('Transit') }}: {{ number_format($settlement['transit_pool'] + $settlement['driver_bounties'], 2) }} €
                @if($diveInvoice > 0) — {{ __('Dive invoice') }}: {{ number_format($diveInvoice, 2) }} € @endif
                @if($totalRefunds > 0) — {{ __('Refunds') }}: {{ number_format($totalRefunds, 2) }} € @endif
            </div>
            <h4 class="mb-0 {{ $netResult >= 0 ? 'text-success' : 'text-danger' }}">
                {{ $netResult >= 0 ? '+' : '' }}{{ number_format($netResult, 2) }} €
            </h4>
        </div>
        @if($diveInvoice > 0 || $totalIndividualCharged > 0)
            <small class="text-muted d-block mt-1">
                🤿 {{ __('Diving') }}: {{ __('invoiced') }} {{ number_format($diveInvoice, 2) }} € — {{ __('charged to participants') }} {{ number_format($totalIndividualCharged, 2) }} €
                @if($diveDelta >= 0)
                    — <span class="text-success">{{ __('covered') }} (+{{ number_format($diveDelta, 2) }} €)</span>
                @else
                    — <span class="text-danger">{{ __('shortfall') }} ({{ number_format($diveDelta, 2) }} €)</span>
                @endif
            </small>
        @endif
    </div>
    {{-- Pending Receipts --}}
    @if($pendingReceipts->isNotEmpty())
    <div class="card dc-card mb-4">
        <div class="card-header bg-warning bg-opacity-10">
            <h5 class="mb-0">{{ __('Pending Receipts') }} ({{ $pendingReceipts->count() }})</h5>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Member') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('Image') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingReceipts as $r)
                    <tr>
                        <td>{{ $r->user->detail?->first_name }} {{ $r->user->detail?->last_name }}</td>
                        <td>{{ number_format($r->amount, 2) }} €</td>
                        <td>{{ match($r->category) { 'general' => __('General'), 'transit' => __('Transit'), 'individual' => __('Individual charge'), 'diving' => __('Diving'), default => $r->category } }}</td>
                        <td>{{ $r->description ?? '—' }}</td>
                        <td>
                            @if($r->image_path)
                                <a href="{{ route('events.settlement.receipt-image', [$event, $r]) }}" target="_blank">📎</a>
                            @endif
                        </td>
                        <td>
                            <form action="{{ route('events.settlement.approve', [$event, $r]) }}" method="POST" class="d-inline-flex gap-1 align-items-center">
                                @csrf
                                <input type="number" step="0.01" name="approved_amount" value="{{ $r->amount }}" class="form-control form-control-sm" style="width:90px" required>
                                <select name="category" class="form-select form-select-sm" style="width:100px">
                                    <option value="general" {{ $r->category === 'general' ? 'selected' : '' }}>{{ __('General') }}</option>
                                    <option value="transit" {{ $r->category === 'transit' ? 'selected' : '' }}>{{ __('Transit') }}</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-success">✓</button>
                            </form>
                            <form action="{{ route('events.settlement.reject', [$event, $r]) }}" method="POST" class="d-inline">
                                @csrf
                                <input type="text" name="reviewer_notes" placeholder="{{ __('Reason') }}" class="form-control form-control-sm d-inline-block" style="width:120px">
                                <button type="submit" class="btn btn-sm btn-outline-danger">✕</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Quick Add Expense (Bureau) --}}
    @if($event->settlement_status === 'open')
    <div class="row mb-4">
        {{-- Van Configuration + Day Rate --}}
        <div class="col-md-4">
            <div class="card dc-card h-100">
                <div class="card-header"><h6 class="mb-0">🚐 {{ __('Vans') }}</h6></div>
                <div class="card-body">
                    <form action="{{ route('events.settlement.update-vans', $event) }}" method="POST" class="d-flex gap-2 align-items-end mb-3">
                        @csrf
                        <div>
                            <label class="form-label form-label-sm">{{ __('Number of vans') }}</label>
                            <input type="number" name="van_count" value="{{ $event->van_count ?? 0 }}" min="0" max="10" class="form-control form-control-sm" style="width:70px">
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Set') }}</button>
                    </form>
                    <div class="d-flex gap-2 align-items-end">
                        <div>
                            <label class="form-label form-label-sm">{{ __('Day rate (local transit)') }}</label>
                            <div class="input-group input-group-sm" style="width:120px">
                                <input type="number" step="0.01" id="day-rate-input" value="{{ $event->local_daily_charge ?? 0 }}" min="0" class="form-control form-control-sm">
                                <span class="input-group-text">€</span>
                            </div>
                        </div>
                        <span id="day-rate-status" class="text-muted small"></span>
                    </div>
                </div>
            </div>
        </div>
        {{-- Add Expense --}}
        <div class="col-md-8">
            <div class="card dc-card h-100">
                <div class="card-header"><h6 class="mb-0">{{ __('Add Expense / Charge') }}</h6></div>
                <div class="card-body">
                    <form action="{{ route('events.settlement.bureau-receipt', $event) }}" method="POST" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-auto">
                            <label class="form-label form-label-sm">{{ __('Category') }}</label>
                            <select name="category" class="form-select form-select-sm" required id="add-category">
                                <option value="general">📦 {{ __('General (shared equally)') }}</option>
                                <option value="transit">🚐 {{ __('Transit (van riders)') }}</option>
                                <option value="diving">🤿 {{ __('Diving (club invoice)') }}</option>
                                <option value="individual">👤 {{ __('Individual charge') }}</option>
                            </select>
                        </div>
                        <div class="col-auto" id="add-member-col">
                            <label class="form-label form-label-sm">{{ __('Paid by / Charged to') }}</label>
                            <select name="user_id" id="add-user-id" class="form-select form-select-sm">
                                <option value="">— {{ __('Club expense') }} —</option>
                                @foreach($event->tripParticipants->sortBy(fn($tp) => $tp->participantName()) as $tp)
                                    <option value="{{ $tp->user_id ?? 'nm:'.$tp->id }}">{{ $tp->participantName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <label class="form-label form-label-sm">{{ __('Amount') }}</label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" name="amount" min="0.01" required class="form-control" style="width:100px">
                                <span class="input-group-text">€</span>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label form-label-sm">{{ __('Description') }}</label>
                            <input type="text" name="description" class="form-control form-control-sm" placeholder="{{ __('e.g. Fuel A7 Lyon, Extra drinks bar') }}" required>
                        </div>
                        <input type="hidden" name="is_third_party" value="0" id="add-third-party-val">
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Add') }}</button>
                        </div>
                    </form>
                    <small class="text-muted mt-2 d-block">
                        <strong>{{ __('General') }}</strong>: {{ __('split equally among all') }} •
                        <strong>{{ __('Transit') }}</strong>: {{ __('split among van riders') }} •
                        <strong>{{ __('Diving') }}</strong>: {{ __('club invoice from dive center') }} •
                        <strong>{{ __('Individual') }}</strong>: {{ __('charged only to the selected person') }}
                    </small>
                </div>
            </div>
        </div>
    </div>
    {{-- Record Prepayments --}}
    <div class="card dc-card mb-4">
        <div class="card-header"><h6 class="mb-0">💳 {{ __('Record Prepayment') }}</h6></div>
        <div class="card-body">
            <form action="{{ route('events.settlement.prepayment', $event) }}" method="POST" class="row g-2 align-items-end">
                @csrf
                <div class="col-auto">
                    <label class="form-label form-label-sm">{{ __('Participant') }}</label>
                    <select name="participant_id" class="form-select form-select-sm" required>
                        @foreach($event->tripParticipants->sortBy(fn($tp) => $tp->participantName()) as $tp)
                            @php
                                $existing = $tp->user_id
                                    ? \App\Models\PaymentExpected::where('event_id', $event->id)->where('user_id', $tp->user_id)->value('amount_paid')
                                    : $tp->prepaid_amount;
                            @endphp
                            <option value="{{ $tp->id }}">{{ $tp->participantName() }}{{ $existing ? ' (€'.number_format((float)$existing, 2).')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label form-label-sm">{{ __('Amount') }}</label>
                    <div class="input-group input-group-sm" style="width:120px">
                        <input type="number" step="0.01" name="amount" min="0" required class="form-control">
                        <span class="input-group-text">€</span>
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Record') }}</button>
                </div>
            </form>
            <small class="text-muted mt-1 d-block">{{ __('Records a deposit/prepayment that reduces the participant\'s balance.') }}</small>
        </div>
    </div>
    @endif
    <div class="card dc-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0 d-inline">{{ __('Participants') }}</h5>
                @php
                    $vanCount = $event->van_count ?? 0;
                    if ($vanCount > 0) {
                        $perVan = $event->tripParticipants->where('van_number', '>', 0)->groupBy('van_number');
                    }
                @endphp
                @if($vanCount > 0)
                    @for($v = 1; $v <= $vanCount; $v++)
                        @php $vanTotal = ($perVan[$v] ?? collect())->sum('driving_percentage'); @endphp
                        @if($vanTotal > 0 && abs($vanTotal - 100) > 5)
                            <span class="badge bg-warning text-dark ms-2" title="{{ __('Should total ~100%') }}">⚠️ {{ __('Van') }} {{ $v }}: {{ $vanTotal }}%</span>
                        @elseif($vanTotal > 0)
                            <span class="badge bg-success ms-2">{{ __('Van') }} {{ $v }}: {{ $vanTotal }}% ✓</span>
                        @endif
                    @endfor
                @else
                    @php $drivingTotal = $event->tripParticipants->sum('driving_percentage'); @endphp
                    @if($drivingTotal > 0 && abs($drivingTotal - 100) > 5)
                        <span class="badge bg-warning text-dark ms-2" title="{{ __('Should total ~100%') }}">⚠️ {{ __('Driving total') }}: {{ $drivingTotal }}%</span>
                    @elseif($drivingTotal > 0)
                        <span class="badge bg-success ms-2">{{ __('Driving') }}: {{ $drivingTotal }}% ✓</span>
                    @endif
                @endif
            </div>
            @if($event->settlement_status === 'open')
                <span id="save-status" class="text-muted small"></span>
            @endif
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm" id="participants-table">
                <thead>
                    <tr>
                        <th class="sortable-col" data-col="0" role="button">{{ __('Name') }} <span class="sort-icon">↕</span></th>
                        <th class="sortable-col" data-col="1" role="button">{{ __('Mode') }} <span class="sort-icon">↕</span></th>
                        @if($event->van_count)
                            <th class="sortable-col" data-col="2" role="button">{{ __('Van') }} <span class="sort-icon">↕</span></th>
                        @endif
                        <th class="sortable-col" data-col="{{ $event->van_count ? 3 : 2 }}" role="button">{{ __('Driving %') }} <span class="sort-icon">↕</span></th>
                        <th class="sortable-col" data-col="{{ $event->van_count ? 4 : 3 }}" role="button">{{ __('Local Days') }} <span class="sort-icon">↕</span></th>
                        <th class="sortable-col" data-col="{{ $event->van_count ? 5 : 4 }}" role="button">{{ __('Dive Costs') }} <span class="sort-icon">↕</span></th>
                        <th class="sortable-col" data-col="{{ $event->van_count ? 6 : 5 }}" role="button">{{ __('Balance') }} <span class="sort-icon">↕</span></th>
                    </tr>
                </thead>
                <tbody>
                    @php $tripDays = $event->event_date->diffInDays($event->end_date ?? $event->event_date) ?: 1; @endphp
                    @foreach($event->tripParticipants as $tp)
                    @php $pResult = $tp->user_id
                        ? collect($settlement['participants'])->firstWhere('user_id', $tp->user_id)
                        : collect($settlement['participants'])->first(fn($p) => $p['user_id'] === null && $p['name'] === $tp->non_member_name); @endphp
                    <tr data-participant-id="{{ $tp->id }}" data-url="{{ route('events.settlement.update-participant', [$event, $tp]) }}">
                        <td>{{ $tp->participantName() }}@if($tp->isNonMember()) <span class="badge bg-secondary" style="font-size:0.6rem">{{ __('non-member') }}</span>@endif</td>
                        @if($event->settlement_status === 'open')
                        <td>
                            <select name="transit_mode" class="form-select form-select-sm auto-save" style="width:80px">
                                <option value="van" {{ ($pResult['transit_mode'] ?? '') === 'van' ? 'selected' : '' }}>🚐</option>
                                <option value="own" {{ ($pResult['transit_mode'] ?? '') === 'own' ? 'selected' : '' }}>🚗</option>
                                <option value="fly" {{ ($pResult['transit_mode'] ?? '') === 'fly' ? 'selected' : '' }}>✈️</option>
                            </select>
                        </td>
                        @if($event->van_count)
                        <td>
                            <select name="van_number" class="form-select form-select-sm auto-save" style="width:80px">
                                <option value="">—</option>
                                @for($v = 1; $v <= $event->van_count; $v++)
                                    <option value="{{ $v }}" {{ $tp->van_number == $v ? 'selected' : '' }}>{{ $v }}</option>
                                @endfor
                            </select>
                        </td>
                        @endif
                        <td>
                            <div class="input-group input-group-sm" style="width:90px">
                                <input type="number" name="driving_percentage" value="{{ $tp->driving_percentage }}" min="0" max="100" class="form-control form-control-sm auto-save">
                                <span class="input-group-text">%</span>
                            </div>
                        </td>
                        <td>
                            <div class="input-group input-group-sm" style="width:90px">
                                <input type="number" name="local_transit_days" value="{{ $tp->local_transit_days }}" min="0" max="{{ $tripDays }}" class="form-control form-control-sm auto-save" {{ ($pResult['transit_mode'] ?? '') === 'van' ? 'disabled' : '' }}>
                                <span class="input-group-text">/{{ $tripDays }}</span>
                            </div>
                        </td>
                        @else
                        <td>{{ ($pResult['transit_mode'] ?? '') === 'van' ? '🚐' : (($pResult['transit_mode'] ?? '') === 'fly' ? '✈️' : '🚗') }}</td>
                        @if($event->van_count)
                            <td>{{ $tp->van_number ? 'Van '.$tp->van_number : '—' }}</td>
                        @endif
                        <td>{{ $tp->driving_percentage }}%</td>
                        <td>{{ $tp->local_transit_days }}d</td>
                        @endif
                        <td>{{ ($pResult['individual_charges'] ?? 0) > 0 ? number_format($pResult['individual_charges'], 2) . ' €' : '—' }}</td>
                        <td class="{{ ($pResult['balance'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($pResult['balance'] ?? 0, 2) }} €
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($event->settlement_status === 'open')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let saveTimeout = null;
        const status = document.getElementById('save-status');

        document.getElementById('participants-table').addEventListener('change', function(e) {
            const el = e.target;
            if (!el.classList.contains('auto-save')) return;

            const row = el.closest('tr');
            const url = row.dataset.url;

            // Disable local_transit_days if switching to van
            if (el.name === 'transit_mode') {
                const ltd = row.querySelector('[name="local_transit_days"]');
                if (ltd) {
                    ltd.disabled = (el.value === 'van');
                    if (el.value === 'van') ltd.value = 0;
                }
            }

            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => saveRow(row, url), 300);
        });

        // Also save on blur for number inputs (typing then tabbing away)
        document.getElementById('participants-table').addEventListener('blur', function(e) {
            if (e.target.type === 'number' && e.target.classList.contains('auto-save')) {
                const row = e.target.closest('tr');
                clearTimeout(saveTimeout);
                saveRow(row, row.dataset.url);
            }
        }, true);

        function saveRow(row, url) {
            const data = new FormData();
            data.append('_token', '{{ csrf_token() }}');
            row.querySelectorAll('[name]').forEach(el => {
                if (!el.disabled) data.append(el.name, el.value);
            });
            // Ensure local_transit_days is sent as 0 if disabled
            if (!data.has('local_transit_days')) data.append('local_transit_days', '0');

            status.textContent = '{{ __("Saving...") }}';
            status.className = 'text-muted small';

            fetch(url, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => {
                    if (r.ok) {
                        status.textContent = '✓ {{ __("Saved") }}';
                        status.className = 'text-success small';
                    } else {
                        status.textContent = '✕ {{ __("Error") }}';
                        status.className = 'text-danger small';
                    }
                    setTimeout(() => { status.textContent = ''; }, 3000);
                })
                .catch(() => {
                    status.textContent = '✕ {{ __("Connection error") }}';
                    status.className = 'text-danger small';
                });
        }
    });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Day rate AJAX save
        const dayRateInput = document.getElementById('day-rate-input');
        const dayRateStatus = document.getElementById('day-rate-status');
        if (dayRateInput) {
            let drTimeout = null;
            dayRateInput.addEventListener('change', function() {
                clearTimeout(drTimeout);
                drTimeout = setTimeout(() => {
                    const data = new FormData();
                    data.append('_token', '{{ csrf_token() }}');
                    data.append('local_daily_charge', dayRateInput.value);
                    dayRateStatus.textContent = '{{ __("Saving...") }}';
                    fetch('{{ route("events.settlement.update-day-rate", $event) }}', {
                        method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(r => {
                        dayRateStatus.textContent = r.ok ? '✓' : '✕';
                        dayRateStatus.className = r.ok ? 'text-success small' : 'text-danger small';
                        setTimeout(() => { dayRateStatus.textContent = ''; }, 3000);
                    });
                }, 300);
            });
        }

        // Edit expense modal
        document.querySelectorAll('.edit-expense-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const form = document.getElementById('editExpenseForm');
                form.action = '{{ url("events/".$event->id."/settlement/receipts") }}/' + id;
                document.getElementById('edit-user-id').value = this.dataset.userId;
                document.getElementById('edit-amount').value = this.dataset.amount;
                document.getElementById('edit-category').value = this.dataset.category;
                document.getElementById('edit-description').value = this.dataset.description;
                document.getElementById('edit-third-party').checked = this.dataset.thirdParty === '1';
                toggleEditMember();
                new bootstrap.Modal(document.getElementById('editExpenseModal')).show();
            });
        });

        // Category toggle: for individual = "charged to"; diving/general/transit = hide member (club expense)
        const addCategory = document.getElementById('add-category');
        const addMemberCol = document.getElementById('add-member-col');
        const addThirdPartyVal = document.getElementById('add-third-party-val');
        if (addCategory && addMemberCol) {
            addCategory.addEventListener('change', function() {
                const label = addMemberCol.querySelector('label');
                if (this.value === 'individual') {
                    addMemberCol.style.display = '';
                    document.getElementById('add-user-id').disabled = false;
                    label.textContent = '{{ __("Charged to") }}';
                    addThirdPartyVal.value = '1';
                } else if (this.value === 'diving') {
                    addMemberCol.style.display = 'none';
                    document.getElementById('add-user-id').disabled = true;
                    addThirdPartyVal.value = '1';
                } else {
                    addMemberCol.style.display = '';
                    document.getElementById('add-user-id').disabled = false;
                    label.textContent = '{{ __("Paid by (optional)") }}';
                    addThirdPartyVal.value = '0';
                }
            });
            // Trigger initial state — set label
            const initLabel = addMemberCol.querySelector('label');
            initLabel.textContent = '{{ __("Paid by (optional)") }}';
        }

        const editTp = document.getElementById('edit-third-party');
        const editMemberDiv = document.getElementById('edit-user-id')?.closest('.mb-3');
        function toggleEditMember() {
            if (editMemberDiv) {
                editMemberDiv.style.display = editTp.checked ? 'none' : '';
                document.getElementById('edit-user-id').disabled = editTp.checked;
            }
        }
        if (editTp) editTp.addEventListener('change', toggleEditMember);
    });
    </script>
    @endif
        </div>
    </div>

    {{-- All Expenses (Approved/Rejected) --}}
    @if($approvedReceipts->isNotEmpty())
    <div class="card dc-card mb-4">
        <div class="card-header">
            <h5 class="mb-0">{{ __('All Expenses') }} ({{ $approvedReceipts->count() }})</h5>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Member') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('Status') }}</th>
                        @if($event->settlement_status === 'open')
                            <th>{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($approvedReceipts as $r)
                    <tr>
                        @if($event->settlement_status === 'open' && isset($editingReceipt) && $editingReceipt == $r->id)
                        {{-- Inline edit row is handled via JS below --}}
                        @endif
                        <td>{{ $r->user ? ($r->user->detail?->first_name . ' ' . $r->user->detail?->last_name) : __('Club (3rd party)') }}</td>
                        <td class="{{ $r->category === 'individual' ? 'text-success' : '' }}">{{ $r->category === 'individual' ? '-' : '' }}{{ number_format($r->approved_amount ?? $r->amount, 2) }} €</td>
                        <td><span class="{{ $r->category === 'individual' ? 'text-success fw-bold' : '' }}">{{ match($r->category) { 'general' => __('General'), 'transit' => __('Transit'), 'individual' => __('Individual charge'), 'diving' => __('Diving'), default => $r->category } }}</span></td>
                        <td>{{ $r->description ?? '—' }}</td>
                        <td>
                            @if($r->status === 'approved')
                                <span class="badge bg-success">{{ __('Approved') }}</span>
                            @else
                                <span class="badge bg-danger">{{ __('Rejected') }}</span>
                            @endif
                            @if($r->is_third_party)
                                <span class="badge bg-info">{{ __('3rd party') }}</span>
                            @endif
                        </td>
                        @if($event->settlement_status === 'open')
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary edit-expense-btn"
                                data-id="{{ $r->id }}"
                                data-user-id="{{ $r->user_id }}"
                                data-amount="{{ $r->approved_amount ?? $r->amount }}"
                                data-category="{{ $r->category }}"
                                data-description="{{ $r->description }}"
                                data-third-party="{{ $r->is_third_party ? '1' : '0' }}">✏️</button>
                            <form action="{{ route('events.settlement.destroy-receipt', [$event, $r]) }}" method="POST" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="{{ __('Delete this expense?') }}">🗑️</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                    @if($settlement['driver_bounties'] > 0)
                    <tr class="table-light">
                        <td>{{ __('Club') }}</td>
                        <td>{{ number_format($settlement['driver_bounties'], 2) }} €</td>
                        <td>{{ __('Transit') }}</td>
                        <td>{{ __('Driver Bounties') }} ({{ $event->tripParticipants->where('driving_percentage', '>', 0)->map(fn($tp) => $tp->participantName() . ' ' . $tp->driving_percentage . '%')->implode(', ') }})</td>
                        <td><span class="badge bg-info">{{ __('Auto') }}</span></td>
                        @if($event->settlement_status === 'open')<td></td>@endif
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    {{-- Edit Expense Modal --}}
    @if($event->settlement_status === 'open')
    <div class="modal fade" id="editExpenseModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" id="editExpenseForm" class="modal-content">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Edit Expense') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Member') }}</label>
                        <select name="user_id" id="edit-user-id" class="form-select" required>
                            @foreach($event->tripParticipants->sortBy(fn($tp) => $tp->participantName()) as $tp)
                                <option value="{{ $tp->user_id ?? 'nm:'.$tp->id }}">{{ $tp->participantName() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Amount') }}</label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="amount" id="edit-amount" min="0.01" required class="form-control">
                            <span class="input-group-text">€</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Category') }}</label>
                        <select name="category" id="edit-category" class="form-select" required>
                            <option value="transit">🚐 {{ __('Transit (fuel, tolls)') }}</option>
                            <option value="general">📦 {{ __('General (shared)') }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Description') }}</label>
                        <input type="text" name="description" id="edit-description" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="hidden" name="is_third_party" value="0">
                            <input type="checkbox" name="is_third_party" value="1" class="form-check-input" id="edit-third-party">
                            <label class="form-check-label" for="edit-third-party">{{ __('Third-party invoice') }}</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
    @endif
    @endif

    {{-- Final Settlement Table --}}
    <div class="card dc-card">
        <div class="card-header"><h5 class="mb-0">{{ __('Settlement Ledger') }}</h5></div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-striped" id="ledger-table">
                <thead>
                    <tr>
                        <th class="sortable-col" data-col="0" role="button" style="cursor:pointer">{{ __('Name') }} <span class="sort-icon">↕</span></th>
                        <th class="sortable-col" data-col="1" role="button" style="cursor:pointer">{{ __('Mode') }} <span class="sort-icon">↕</span></th>
                        <th class="sortable-col text-end" data-col="2" role="button" style="cursor:pointer">{{ __('Global') }} <span class="sort-icon">↕</span></th>
                        <th class="sortable-col text-end" data-col="3" role="button" style="cursor:pointer">{{ __('Transit') }} <span class="sort-icon">↕</span></th>
                        <th class="sortable-col text-end" data-col="4" role="button" style="cursor:pointer">{{ __('Dive Costs') }} <span class="sort-icon">↕</span></th>
                        <th class="sortable-col text-end" data-col="5" role="button" style="cursor:pointer">{{ __('Bounty') }} <span class="sort-icon">↕</span></th>
                        <th class="sortable-col text-end" data-col="6" role="button" style="cursor:pointer">{{ __('Prepaid') }} <span class="sort-icon">↕</span></th>
                        <th class="sortable-col text-end" data-col="7" role="button" style="cursor:pointer">{{ __('Paid') }} <span class="sort-icon">↕</span></th>
                        <th class="sortable-col text-end" data-col="8" role="button" style="cursor:pointer">{{ __('Balance') }} <span class="sort-icon">↕</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($settlement['participants'] as $p)
                    <tr>
                        <td>{{ $p['name'] }}</td>
                        <td>
                            @if($p['transit_mode'] === 'van') 🚐
                            @elseif($p['transit_mode'] === 'fly') ✈️
                            @else 🚗
                            @endif
                        </td>
                        <td class="text-end">{{ number_format($p['global_share'], 2) }}</td>
                        <td class="text-end">{{ number_format($p['transit_share'] + ($p['local_charge'] ?? 0), 2) }}</td>
                        <td class="text-end">{{ ($p['individual_charges'] ?? 0) > 0 ? number_format($p['individual_charges'], 2) : '—' }}</td>
                        <td class="text-end">{{ $p['bounty_credit'] > 0 ? '-' . number_format($p['bounty_credit'], 2) : '—' }}</td>
                        <td class="text-end">{{ $p['prepaid'] > 0 ? '-' . number_format($p['prepaid'], 2) : '—' }}</td>
                        <td class="text-end">{{ $p['total_paid'] > 0 ? number_format($p['total_paid'], 2) : '—' }}</td>
                        <td class="text-end fw-bold {{ $p['balance'] > 0 ? 'text-danger' : ($p['balance'] < 0 ? 'text-success' : '') }}">
                            {{ $p['balance'] >= 0 ? '' : '-' }}{{ number_format(abs($p['balance']), 2) }} €
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-dark">
                        <td colspan="8" class="text-end fw-bold">{{ __('Total') }}</td>
                        <td class="text-end fw-bold">{{ number_format(collect($settlement['participants'])->sum('balance'), 2) }} €</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-layout>
<script>
document.addEventListener('DOMContentLoaded', function() {
    function makeTableSortable(tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;
        const tbody = table.querySelector('tbody');
        let sortCol = -1, sortAsc = true;

        function getCellValue(row, col) {
            const cell = row.cells[col];
            if (!cell) return '';
            const input = cell.querySelector('input[type="number"]');
            if (input) return parseFloat(input.value) || 0;
            const select = cell.querySelector('select');
            if (select) return select.value;
            return cell.textContent.trim().replace(/[€,%—]/g, '').trim();
        }

        function compare(a, b, col) {
            let va = getCellValue(a, col), vb = getCellValue(b, col);
            const na = parseFloat(va), nb = parseFloat(vb);
            if (!isNaN(na) && !isNaN(nb)) return na - nb;
            return String(va).localeCompare(String(vb), undefined, {sensitivity: 'base'});
        }

        table.querySelectorAll('.sortable-col').forEach(th => {
            th.style.cursor = 'pointer';
            th.addEventListener('click', function() {
                const col = parseInt(this.dataset.col);
                const active = document.activeElement;
                if (active && active.classList.contains('auto-save')) active.blur();

                if (sortCol === col) { sortAsc = !sortAsc; }
                else { sortCol = col; sortAsc = true; }

                const rows = Array.from(tbody.querySelectorAll('tr'));
                rows.sort((a, b) => compare(a, b, col) * (sortAsc ? 1 : -1));
                rows.forEach(r => tbody.appendChild(r));

                table.querySelectorAll('.sort-icon').forEach(s => s.textContent = '↕');
                this.querySelector('.sort-icon').textContent = sortAsc ? '↑' : '↓';
            });
        });
    }
    makeTableSortable('participants-table');
    makeTableSortable('ledger-table');
});
</script>
