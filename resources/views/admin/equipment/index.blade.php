<x-layout :title="__('Equipment Inventory')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">{{ __('Equipment Inventory') }}</h4>
        <a href="{{ route('admin.equipment.create') }}" class="btn btn-sm btn-primary">{{ __('Add Equipment') }}</a>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-2">
            <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('All Types') }}</option>
                @foreach(['bcd','regulator','tank','wetsuit','mask','fins','computer','other'] as $t)
                    <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
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
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Serial') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Status') }}</th><th>{{ __('Loaned To') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($equipment as $e)
                <tr>
                    <td>{{ $e->name }}</td>
                    <td><span class="badge bg-secondary">{{ ucfirst($e->type) }}</span></td>
                    <td class="small">{{ $e->serial_number ?? '—' }}</td>
                    <td>{{ ucfirst($e->condition) }}</td>
                    <td><span class="badge bg-{{ $e->status === 'available' ? 'success' : ($e->status === 'on_loan' ? 'info' : ($e->status === 'maintenance_required' ? 'danger' : 'secondary')) }}">{{ ucfirst(str_replace('_', ' ', $e->status)) }}</span></td>
                    <td>{{ $e->currentLoan?->user?->name ?? '—' }}</td>
                    <td><a href="{{ route('admin.equipment.show', $e) }}" class="btn btn-sm btn-outline-primary">{{ __('View') }}</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $equipment->links() }}
</x-layout>
