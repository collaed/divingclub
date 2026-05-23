<x-layout :title="__('Trip Settlement') . ' — ' . $event->title">
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('events.index') }}">{{ __('Calendar') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('events.show', $event) }}">{{ $event->title }}</a></li>
        <li class="breadcrumb-item active">{{ __('Settlement') }}</li>
    </ol></nav>

    <div class="row">
        {{-- My Balance --}}
        <div class="col-lg-4 mb-4">
            <div class="card dc-card">
                <div class="card-header"><h5 class="mb-0">{{ __('My Balance') }}</h5></div>
                <div class="card-body text-center">
                    @if($myBalance)
                        <h2 class="{{ $myBalance['balance'] > 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format(abs($myBalance['balance']), 2) }} €
                        </h2>
                        <p class="text-muted mb-0">
                            @if($myBalance['balance'] > 0)
                                {{ __('You owe the club') }}
                            @elseif($myBalance['balance'] < 0)
                                {{ __('The club owes you') }}
                            @else
                                {{ __('Settled') }}
                            @endif
                        </p>
                        <hr>
                        <small class="text-muted">
                            {{ __('Global share') }}: {{ number_format($myBalance['global_share'], 2) }} € <br>
                            @if($myBalance['transit_share'] > 0)
                                {{ __('Transit share') }}: {{ number_format($myBalance['transit_share'], 2) }} € <br>
                            @endif
                            @if($myBalance['local_charge'] > 0)
                                {{ __('Local transit') }}: {{ number_format($myBalance['local_charge'], 2) }} € <br>
                            @endif
                            @if($myBalance['bounty_credit'] > 0)
                                {{ __('Driver bounty') }}: -{{ number_format($myBalance['bounty_credit'], 2) }} € <br>
                            @endif
                            {{ __('You paid') }}: {{ number_format($myBalance['total_paid'], 2) }} €
                        </small>
                    @else
                        <p class="text-muted">{{ __('You are not a participant in this trip settlement.') }}</p>
                    @endif
                </div>
            </div>

            @if($event->settlement_status === 'closed')
                <div class="alert alert-info mt-3">{{ __('This ledger is closed. No further changes.') }}</div>
            @endif
        </div>

        {{-- Submit Receipt --}}
        <div class="col-lg-8 mb-4">
            @if($event->settlement_status === 'open')
            <div class="card dc-card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Submit a Receipt') }}</h5></div>
                <div class="card-body">
                    <form action="{{ route('events.settlement.store-receipt', $event) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3">
                            <div class="col-sm-4">
                                <label class="form-label">{{ __('Amount (€)') }}</label>
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                                @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label">{{ __('Category') }}</label>
                                <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                                    <option value="general" {{ old('category') === 'general' ? 'selected' : '' }}>{{ __('General (shared)') }}</option>
                                    <option value="transit" {{ old('category') === 'transit' ? 'selected' : '' }}>{{ __('Transit (van costs)') }}</option>
                                </select>
                                @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label">{{ __('Photo/PDF') }}</label>
                                <input type="file" name="image" class="form-control @error('image') is-invalid @enderror" accept="image/*,.pdf">
                                @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('Description') }}</label>
                                <input type="text" name="description" class="form-control" value="{{ old('description') }}" placeholder="{{ __('e.g. Fuel station A6, Toll Lyon-Nice') }}">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">{{ __('Submit Receipt') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            @endif

            {{-- My Receipts --}}
            <div class="card dc-card">
                <div class="card-header"><h5 class="mb-0">{{ __('My Receipts') }}</h5></div>
                <div class="card-body">
                    @if($receipts->isEmpty())
                        <p class="text-muted">{{ __('No receipts submitted yet.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Amount') }}</th>
                                        <th>{{ __('Category') }}</th>
                                        <th>{{ __('Description') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($receipts as $r)
                                    <tr>
                                        <td>{{ $r->created_at->format('d/m') }}</td>
                                        <td>{{ number_format($r->amount, 2) }} €</td>
                                        <td>
                                            @if($r->category === 'general')
                                                <span class="badge bg-info">{{ __('General') }}</span>
                                            @else
                                                <span class="badge bg-warning text-dark">{{ __('Transit') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $r->description ?? '—' }}</td>
                                        <td>
                                            @if($r->status === 'pending')
                                                <span class="badge bg-secondary">{{ __('Pending') }}</span>
                                            @elseif($r->status === 'approved')
                                                <span class="badge bg-success">{{ __('Approved') }} ({{ number_format($r->approved_amount, 2) }} €)</span>
                                            @else
                                                <span class="badge bg-danger">{{ __('Rejected') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($r->status === 'pending' && $event->settlement_status === 'open')
                                                <form action="{{ route('events.settlement.delete-receipt', [$event, $r]) }}" method="POST" class="d-inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="{{ __('Delete this receipt?') }}">✕</button>
                                                </form>
                                            @endif
                                            @if($r->image_path)
                                                <a href="{{ route('events.settlement.receipt-image', [$event, $r]) }}" target="_blank" class="btn btn-sm btn-outline-secondary">📎</a>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Full Settlement Table (read-only for members) --}}
    @if(!empty($settlement['participants']))
    <div class="card dc-card mt-3">
        <div class="card-header"><h5 class="mb-0">{{ __('Settlement Overview') }}</h5></div>
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
                    <tr class="{{ $p['user_id'] === auth()->id() ? 'table-active fw-bold' : '' }}">
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
                        <td class="text-end {{ $p['balance'] > 0 ? 'text-danger' : ($p['balance'] < 0 ? 'text-success' : '') }}">
                            {{ $p['balance'] >= 0 ? '' : '-' }}{{ number_format(abs($p['balance']), 2) }} €
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <small class="text-muted">
                {{ __('Positive = you owe the club. Negative = the club owes you.') }}
            </small>
        </div>
    </div>
    @endif
</x-layout>
