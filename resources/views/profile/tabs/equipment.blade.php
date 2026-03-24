@php
    $loans = $target->equipmentLoans()->whereNull('returned_at')->with('equipment')->get();
    $isBureau = $viewer->isBureau();
@endphp

<h6>{{ __('Equipment Currently on Loan') }}</h6>
@if($loans->count())
    <table class="table table-sm">
        <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Type') }}</th><th>{{ __('Since') }}</th>@if($isBureau)<th></th>@endif</tr></thead>
        <tbody>
        @foreach($loans as $loan)
            <tr>
                <td>{{ $loan->equipment->name }}</td>
                <td><span class="badge bg-secondary">{{ ucfirst($loan->equipment->type) }}</span></td>
                <td>{{ $loan->loaned_at->format('d/m/Y') }}</td>
                @if($isBureau)
                <td><form method="POST" action="{{ route('admin.equipment.return', $loan) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success py-0">{{ __('Return') }}</button></form></td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    <p class="text-muted">{{ __('No equipment currently on loan.') }}</p>
@endif

@if($isBureau)
    <hr>
    <h6>{{ __('Quick Loan') }}</h6>
    @php
        $available = \App\Models\Equipment::where('status', 'available')->orderBy('name')->get();
        $cylinders = $available->filter(fn($e) => in_array($e->type, ['tank', 'tank_air', 'tank_nitrox']));
        $bcds = $available->where('type', 'bcd');
        $regulators = $available->where('type', 'regulator');
    @endphp
    <form method="POST" action="{{ route('admin.equipment.quick-loan') }}">
        @csrf
        <input type="hidden" name="user_id" value="{{ $target->id }}">
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label">🔵 {{ __('Cylinder') }}</label>
                <select name="cylinder_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach($cylinders as $c)
                        <option value="{{ $c->id }}">{{ $c->short_number ?? $c->id }} · {{ $c->name }}@if($c->volume) ({{ $c->volume }}L)@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">🦺 {{ __('BCD') }}</label>
                <select name="bcd_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach($bcds as $b)
                        <option value="{{ $b->id }}">{{ $b->short_number ?? $b->id }} · {{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">🫁 {{ __('Regulator') }}</label>
                <select name="regulator_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach($regulators as $r)
                        <option value="{{ $r->id }}">{{ $r->short_number ?? $r->id }} · {{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-primary w-100">{{ __('Loan') }}</button>
            </div>
        </div>
    </form>
@endif
