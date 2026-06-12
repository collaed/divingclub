<x-admin-layout :title="__('Equipment Inventory')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">{{ __('Equipment Inventory') }}</h4>
        <a href="{{ route('admin.equipment.create') }}" class="btn btn-sm btn-primary">{{ __('Add Equipment') }}</a>
    </div>

    @php
        $typeCounts = \App\Models\Equipment::query()->selectRaw('type, count(*) as cnt')->groupBy('type')->pluck('cnt', 'type');
        $locations = \App\Models\Equipment::query()->whereNotNull('location')->where('location', '!=', '')->distinct()->pluck('location')->sort();
    @endphp
    <form method="GET" class="d-flex flex-wrap gap-2 mb-3 align-items-center">
        <div style="min-width:140px;flex:1">
            <input type="text" name="search" data-instant-search="table-equipment" class="form-control form-control-sm" placeholder="{{ __('Search name, serial, #…') }}" value="{{ request('search') }}">
        </div>
        <div>
            <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('All Types') }} ({{ $typeCounts->sum() }})</option>
                @foreach(['bcd','regulator','tank','wetsuit','mask','fins','computer','other'] as $t)
                    @if($typeCounts->get($t, 0) > 0 || request('type') === $t)
                        <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ ucfirst($t) }} ({{ $typeCounts->get($t, 0) }})</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div>
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('All Statuses') }}</option>
                @foreach(['available','on_loan','maintenance_required','retired'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <select name="location" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('All Locations') }}</option>
                @foreach($locations as $loc)
                    <option value="{{ $loc }}" {{ request('location') === $loc ? 'selected' : '' }}>{{ $loc }}</option>
                @endforeach
            </select>
        </div>
        <div style="width:100px">
            <input type="text" name="size" class="form-control form-control-sm" placeholder="{{ __('Size…') }}" value="{{ request('size') }}" onchange="this.form.submit()">
        </div>
        <div>
            <button class="btn btn-sm btn-outline-primary">{{ __('Filter') }}</button>
            @if(request()->hasAny(['search','type','status','location','size']))
                <a href="{{ route('admin.equipment.index') }}" class="btn btn-sm btn-outline-secondary">✕</a>
            @endif
        </div>
    </form>

    <div class="table-responsive">
        <table id="table-equipment" class="table table-sm table-hover">
            <thead><tr>
                <th><x-sortable-th column="short_number" :label="'#'" /></th>
                <th><x-sortable-th column="name" :label="__('Name')" /></th>
                <th><x-sortable-th column="type" :label="__('Type')" /></th>
                <th><x-sortable-th column="location" :label="__('Location')" /></th>
                <th><x-sortable-th column="status" :label="__('Status')" /></th>
                <th>{{ __('Loaned To') }}</th>
                <th><x-sortable-th column="last_seen_at" :label="__('Last Seen')" /></th>
            </tr></thead>
            <tbody>
            @foreach($equipment as $e)
                <tr data-href="{{ route('admin.equipment.show', $e) }}">
                    <td class="fw-bold">{{ $e->short_number ?? '—' }}</td>
                    <td>
                        {{ $e->name }}
                        @if($e->is_child_sized) <span class="badge bg-warning text-dark" style="font-size:.6rem">👶</span> @endif
                        @if($e->is_cold_water) <span class="badge bg-info" style="font-size:.6rem">❄️</span> @endif
                    </td>
                    <td><span class="badge bg-secondary">{{ ucfirst($e->type) }}</span></td>
                    <td class="small">{{ $e->location ?? '—' }}</td>
                    <td><span class="badge bg-{{ $e->status === 'available' ? 'success' : ($e->status === 'on_loan' ? 'info' : ($e->status === 'maintenance_required' ? 'danger' : 'secondary')) }}">{{ ucfirst(str_replace('_', ' ', $e->status)) }}</span></td>
                    <td>
                        @if($e->currentLoan)
                            {{ $e->currentLoan->user?->name ?? '—' }}
                            <form method="POST" action="{{ route('admin.equipment.return', $e->currentLoan) }}" class="d-inline ms-1">@csrf<button class="btn btn-sm btn-outline-success py-0">↩</button></form>
                        @else
                            —
                        @endif
                    </td>
                    <td class="small text-muted">
                        {{ $e->last_seen_at?->format('d/m/y') ?? '—' }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $equipment->links() }}
</x-admin-layout>

@include("components.clickable-rows")
