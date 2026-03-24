<x-layout :title="__('Equipment Inventory')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">{{ __('Equipment Inventory') }}</h4>
        <a href="{{ route('admin.equipment.create') }}" class="btn btn-sm btn-primary">{{ __('Add Equipment') }}</a>
    </div>

    @php $typeCounts = \App\Models\Equipment::query()->selectRaw('type, count(*) as cnt')->groupBy('type')->pluck('cnt', 'type'); @endphp
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('All Types') }} ({{ $typeCounts->sum() }})</option>
                @foreach(['bcd','regulator','tank','wetsuit','mask','fins','computer','other'] as $t)
                    @if($typeCounts->get($t, 0) > 0 || request('type') === $t)
                        <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ ucfirst($t) }} ({{ $typeCounts->get($t, 0) }})</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('All Statuses') }}</option>
                @foreach(['available','on_loan','maintenance_required','retired'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm table-hover">
            @php
                $s = request('sort'); $d = request('dir','asc');
                $arrow = fn($col) => $s === $col ? ($d === 'asc' ? '↑' : '↓') : '';
                $link = fn($col, $label) => '<a href="?sort='.$col.'&dir='.($s === $col && $d === 'asc' ? 'desc' : 'asc').'" class="text-decoration-none text-dark">'.$label.' '.$arrow($col).'</a>';
            @endphp
            <thead><tr><th>{!! $link('short_number', '#') !!}</th><th>{!! $link('name', __('Name')) !!}</th><th>{!! $link('type', __('Type')) !!}</th><th>{{ __('Serial') }}</th><th>{!! $link('location', __('Location')) !!}</th><th>{!! $link('status', __('Status')) !!}</th><th>{!! $link('loaned_to', __('Loaned To')) !!}</th><th></th></tr></thead>
            <tbody>
            @foreach($equipment as $e)
                <tr>
                    <td class="fw-bold">{{ $e->short_number ?? '—' }}</td>
                    <td>{{ $e->name }}</td>
                    <td><span class="badge bg-secondary">{{ ucfirst($e->type) }}</span></td>
                    <td class="small">{{ $e->serial_number ?? '—' }}</td>
                    <td class="small">{{ $e->location ?? '—' }}</td>
                    <td><span class="badge bg-{{ $e->status === 'available' ? 'success' : ($e->status === 'on_loan' ? 'info' : ($e->status === 'maintenance_required' ? 'danger' : 'secondary')) }}">{{ ucfirst(str_replace('_', ' ', $e->status)) }}</span></td>
                    <td>{{ $e->currentLoan?->user?->name ?? '—' }}</td>
                    <td class="text-end text-nowrap">
                        @if($e->currentLoan)
                            <form method="POST" action="{{ route('admin.equipment.return', $e->currentLoan) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success py-0">↩ {{ __('Return') }}</button></form>
                        @endif
                        <a href="{{ route('admin.equipment.show', $e) }}" class="btn btn-sm btn-outline-primary py-0">{{ __('View') }}</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $equipment->links() }}
</x-layout>
