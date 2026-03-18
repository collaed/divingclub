@php $loans = $target->equipmentLoans()->whereNull('returned_at')->with('equipment')->get(); @endphp
<h6>{{ __('Equipment Currently on Loan') }}</h6>
@if($loans->count())
    <table class="table table-sm">
        <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Type') }}</th><th>{{ __('Since') }}</th></tr></thead>
        <tbody>
        @foreach($loans as $loan)
            <tr><td>{{ $loan->equipment->name }}</td><td><span class="badge bg-secondary">{{ ucfirst($loan->equipment->type) }}</span></td><td>{{ $loan->loaned_at->format('d/m/Y') }}</td></tr>
        @endforeach
        </tbody>
    </table>
@else
    <p class="text-muted">{{ __('No equipment currently on loan.') }}</p>
@endif

@php $d = $target->detail; @endphp
<hr>
<h6>{{ __('BCD Size') }}</h6>
<p>{{ $d?->bcd_size ?? __('Not set') }}</p>
