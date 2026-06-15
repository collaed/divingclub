@php
    $loans = $target->equipmentLoans()->whereNull('returned_at')->with('equipment')->get();
    $isBureau = $viewer->isBureau();
    $d = $target->detail;
@endphp

{{-- Sizing preferences --}}
<div class="card dc-card mb-4">
    <div class="card-header"><h6 class="mb-0">{{ __('Sizing') }}</h6></div>
    <div class="card-body">
        <form method="POST" action="{{ route('profile.update-equipment', $target) }}" class="row g-3">
            @csrf
            <div class="col-auto">
                <label class="form-label form-label-sm">{{ __('BCD Size') }}</label>
                <select name="bcd_size" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach(['XXS','XS','S','M','L','XL','XXL'] as $sz)
                        <option value="{{ $sz }}" {{ ($d?->bcd_size ?? '') === $sz ? 'selected' : '' }}>{{ $sz }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm">{{ __('T-Shirt Size') }}</label>
                <select name="tshirt_size" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach(['XS','S','M','L','XL','XXL','3XL'] as $sz)
                        <option value="{{ $sz }}" {{ ($d?->tshirt_size ?? '') === $sz ? 'selected' : '' }}>{{ $sz }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm">{{ __('Suit Brand') }}</label>
                <input type="text" name="suit_brand" class="form-control form-control-sm" value="{{ $d?->suit_brand }}" placeholder="{{ __('e.g. Mares, Scubapro') }}" list="suit-brands-list">
                <datalist id="suit-brands-list">
                    <option value="Mares"><option value="Scubapro"><option value="Cressi"><option value="Aqualung"><option value="Beuchat"><option value="Waterproof"><option value="Bare">
                </datalist>
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm">{{ __('Suit Size') }}</label>
                <input type="text" name="suit_size" class="form-control form-control-sm" value="{{ $d?->suit_size }}" placeholder="{{ __('e.g. ML, 5/4 L') }}" style="width:100px">
            </div>
            <div class="col-auto align-self-end">
                <button class="btn btn-sm btn-primary">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>

<h6>{{ __('Equipment Currently on Loan') }}</h6>
@if($loans->count())
    <table class="table table-sm">
        <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Type') }}</th><th>{{ __('Since') }}</th>@if($isBureau)<th></th>@endif</tr></thead>
        <tbody>
        @foreach($loans as $loan)
            @if($loan->equipment)
            <tr>
                <td>{{ $loan->equipment->name }}</td>
                <td><span class="badge bg-secondary">{{ ucfirst($loan->equipment->type) }}</span></td>
                <td>{{ $loan->loaned_at->format('d/m/Y') }}</td>
                @if($isBureau)
                <td><form method="POST" action="{{ route('admin.equipment.return', $loan) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success py-0">{{ __('Return') }}</button></form></td>
                @endif
            </tr>
            @endif
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
        // Sort BCDs by proximity to user's preferred size
        $userSize = $target->detail?->bcd_size;
        if ($userSize && $loanGroups->has('bcd')) {
            $sizeOrder = ['XXXS' => 0, 'XXS' => 1, 'XS' => 2, 'S' => 3, 'M' => 4, 'ML' => 5, 'L' => 6, 'XL' => 7, 'XXL' => 8];
            $userIdx = $sizeOrder[strtoupper($userSize)] ?? 4;
            $loanGroups['bcd'] = $loanGroups['bcd']->sortBy(fn($e) => abs(($sizeOrder[strtoupper($e->volume)] ?? 99) - $userIdx));
        }
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
