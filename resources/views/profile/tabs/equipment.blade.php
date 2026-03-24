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
        $available = \App\Models\Equipment::where('status', 'available')->where('is_loanable', true)->orderBy('short_number')->orderBy('name')->get();
        $loanGroups = $available->groupBy('type');
    @endphp
    <form method="POST" action="{{ route('admin.equipment.quick-loan') }}">
        @csrf
        <input type="hidden" name="user_id" value="{{ $target->id }}">
        <div class="row g-2">
            @foreach($loanGroups as $type => $items)
                <div class="col-md">
                    <label class="form-label">{{ ucfirst($type) }}</label>
                    <select name="equipment_ids[]" class="form-select form-select-sm">
                        <option value="">—</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->short_number ?? $item->id }} · {{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-md-auto d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Loan') }}</button>
            </div>
        </div>
    </form>
@endif
