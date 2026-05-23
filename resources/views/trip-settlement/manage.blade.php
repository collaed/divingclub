<x-layout :title="__('Manage Settlement') . ' — ' . $event->title">
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('events.index') }}">{{ __('Calendar') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('events.show', $event) }}">{{ $event->title }}</a></li>
        <li class="breadcrumb-item active">{{ __('Manage Settlement') }}</li>
    </ol></nav>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>{{ __('Trip Settlement') }} — {{ $event->title }}</h4>
        <div>
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

    {{-- Summary Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card dc-card text-center">
                <div class="card-body">
                    <h5>{{ number_format($settlement['global_pool'], 2) }} €</h5>
                    <small class="text-muted">{{ __('Global Pool') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dc-card text-center">
                <div class="card-body">
                    <h5>{{ number_format($settlement['transit_pool'], 2) }} €</h5>
                    <small class="text-muted">{{ __('Transit Pool') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dc-card text-center">
                <div class="card-body">
                    <h5>{{ number_format($settlement['driver_bounties'], 2) }} €</h5>
                    <small class="text-muted">{{ __('Driver Bounties') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dc-card text-center">
                <div class="card-body">
                    <h5>{{ number_format($settlement['local_subsidy'], 2) }} €</h5>
                    <small class="text-muted">{{ __('Local Subsidy') }}</small>
                </div>
            </div>
        </div>
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
                        <td>{{ $r->category === 'general' ? __('General') : __('Transit') }}</td>
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

    {{-- Participants Management --}}
    <div class="card dc-card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Participants') }}</h5></div>
        <div class="card-body table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Mode') }}</th>
                        <th>{{ __('Driving %') }}</th>
                        <th>{{ __('Local Days') }}</th>
                        <th>{{ __('Balance') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($event->tripParticipants as $tp)
                    @php $pResult = collect($settlement['participants'])->firstWhere('user_id', $tp->user_id); @endphp
                    <tr>
                        <td>{{ $tp->user->detail?->first_name }} {{ $tp->user->detail?->last_name }}</td>
                        <td>{{ $pResult['transit_mode'] ?? '—' }}</td>
                        <td colspan="3">
                            @if($event->settlement_status === 'open')
                            <form action="{{ route('events.settlement.update-participant', [$event, $tp]) }}" method="POST" class="d-inline-flex gap-1 align-items-center">
                                @csrf
                                <input type="number" name="driving_percentage" value="{{ $tp->driving_percentage }}" min="0" max="100" class="form-control form-control-sm" style="width:60px" title="{{ __('Driving %') }}">
                                <input type="number" name="local_transit_days" value="{{ $tp->local_transit_days }}" min="0" max="30" class="form-control form-control-sm" style="width:60px" title="{{ __('Local days') }}">
                                <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Save') }}</button>
                            </form>
                            @else
                                {{ $tp->driving_percentage }}% / {{ $tp->local_transit_days }}d
                            @endif
                        </td>
                        <td class="{{ ($pResult['balance'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($pResult['balance'] ?? 0, 2) }} €
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Final Settlement Table --}}
    <div class="card dc-card">
        <div class="card-header"><h5 class="mb-0">{{ __('Settlement Ledger') }}</h5></div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Mode') }}</th>
                        <th class="text-end">{{ __('Global') }}</th>
                        <th class="text-end">{{ __('Transit') }}</th>
                        <th class="text-end">{{ __('Bounty') }}</th>
                        <th class="text-end">{{ __('Paid') }}</th>
                        <th class="text-end">{{ __('Balance') }}</th>
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
                        <td class="text-end">{{ $p['bounty_credit'] > 0 ? '-' . number_format($p['bounty_credit'], 2) : '—' }}</td>
                        <td class="text-end">{{ $p['total_paid'] > 0 ? number_format($p['total_paid'], 2) : '—' }}</td>
                        <td class="text-end fw-bold {{ $p['balance'] > 0 ? 'text-danger' : ($p['balance'] < 0 ? 'text-success' : '') }}">
                            {{ $p['balance'] >= 0 ? '' : '-' }}{{ number_format(abs($p['balance']), 2) }} €
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-dark">
                        <td colspan="6" class="text-end fw-bold">{{ __('Total') }}</td>
                        <td class="text-end fw-bold">{{ number_format(collect($settlement['participants'])->sum('balance'), 2) }} €</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-layout>
