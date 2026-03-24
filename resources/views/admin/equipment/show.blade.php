<x-layout :title="$equipment->name">
    <h4 class="mb-4">{{ $equipment->name }} <span class="badge bg-{{ $equipment->status === 'available' ? 'success' : ($equipment->status === 'on_loan' ? 'info' : 'danger') }}">{{ ucfirst(str_replace('_', ' ', $equipment->status)) }}</span></h4>

    <div class="row">
        <div class="col-lg-6">
            <div class="card dc-card mb-4">
                <div class="card-header">{{ __('Details') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.equipment.update', $equipment) }}">
                        @csrf @method('PUT')
                        <div class="row g-2">
                            <div class="col-md-6"><label class="form-label">{{ __('Name') }}</label><input type="text" name="name" class="form-control form-control-sm" value="{{ $equipment->name }}" required></div>
                            <div class="col-md-3"><label class="form-label">{{ __('Short #') }}</label><input type="text" name="short_number" class="form-control form-control-sm" value="{{ $equipment->short_number }}" maxlength="10" placeholder="e.g. 12, M3"></div>
                            <div class="col-md-3"><label class="form-label">{{ __('Type') }}</label><input type="text" name="type" class="form-control form-control-sm" value="{{ $equipment->type }}" readonly></div>
                            <div class="col-md-4"><label class="form-label">{{ __('Serial') }}</label><input type="text" name="serial_number" class="form-control form-control-sm" value="{{ $equipment->serial_number }}"></div>
                            <div class="col-md-4"><label class="form-label">{{ __('Condition') }}</label>
                                <select name="condition" class="form-select form-select-sm">@foreach(['new','good','fair','poor'] as $c) <option value="{{ $c }}" {{ $equipment->condition === $c ? 'selected' : '' }}>{{ ucfirst($c) }}</option> @endforeach</select>
                            </div>
                            <div class="col-md-4"><label class="form-label">{{ __('Status') }}</label>
                                <select name="status" class="form-select form-select-sm">@foreach(['available','on_loan','maintenance_required','retired'] as $s) <option value="{{ $s }}" {{ $equipment->status === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option> @endforeach</select>
                            </div>
                            <div class="col-12"><textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="{{ __('Notes') }}">{{ $equipment->notes }}</textarea></div>
                            <div class="col-md-6"><label class="form-label">{{ __('Location') }}</label><input type="text" name="location" class="form-control form-control-sm" value="{{ $equipment->location }}" placeholder="e.g. Entrepôt, Piscine Merl"></div>
                            <div class="col-md-6 d-flex align-items-end"><div class="form-check"><input type="hidden" name="is_loanable" value="0"><input type="checkbox" name="is_loanable" value="1" class="form-check-input" id="loanable" {{ $equipment->is_loanable ? 'checked' : '' }}><label class="form-check-label" for="loanable">{{ __('Available for loan') }}</label></div></div>
                        </div>
                        <button class="btn btn-sm btn-primary mt-2">{{ __('Save') }}</button>
                    </form>
                </div>
            </div>

            {{-- Loan --}}
            <div class="card dc-card mb-4">
                <div class="card-header">{{ __('Loan Equipment') }}</div>
                <div class="card-body">
                    @if($equipment->isAvailable())
                        <form method="POST" action="{{ route('admin.equipment.loan', $equipment) }}" class="row g-2">
                            @csrf
                            <div class="col-md-8">
                                <select name="user_id" class="form-select form-select-sm" required>
                                    <option value="">{{ __('Select member...') }}</option>
                                    @foreach($members as $m) <option value="{{ $m->id }}">{{ $m->name }}</option> @endforeach
                                </select>
                            </div>
                            <div class="col-md-4"><button class="btn btn-sm btn-primary w-100">{{ __('Loan') }}</button></div>
                        </form>
                    @else
                        <p class="text-muted mb-0">{{ __('Equipment must be available to loan.') }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            {{-- Maintenance --}}
            <div class="card dc-card mb-4">
                <div class="card-header">{{ __('Maintenance Schedule') }}</div>
                <div class="card-body">
                    @foreach($equipment->maintenanceTasks as $mt)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <div>
                                <strong>{{ $mt->maintenance_name }}</strong>
                                @if($mt->is_mandatory) <span class="badge bg-danger" style="font-size:0.6rem">{{ __('Mandatory') }}</span> @endif
                                <br><small class="text-muted">{{ __('Due') }}: {{ $mt->due_date->format('d/m/Y') }}</small>
                                @if($mt->completed_at) <span class="badge bg-success">{{ __('Done') }} {{ $mt->completed_at->format('d/m/Y') }}</span> @elseif($mt->due_date->isPast()) <span class="badge bg-danger">{{ __('Overdue') }}</span> @endif
                            </div>
                            @if(!$mt->completed_at)
                                <form method="POST" action="{{ route('admin.equipment.maintenance.complete', $mt) }}">@csrf <button class="btn btn-sm btn-success">✓</button></form>
                            @endif
                        </div>
                    @endforeach
                    @if($equipment->maintenanceTasks->isEmpty()) <p class="text-muted mb-0">{{ __('No maintenance tasks.') }}</p> @endif
                </div>
            </div>

            {{-- Loan History --}}
            <div class="card dc-card mb-4">
                <div class="card-header">{{ __('Loan History') }}</div>
                <div class="card-body">
                    @foreach($equipment->loans as $loan)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <div>
                                <strong>{{ $loan->user?->name }}</strong>
                                <br><small class="text-muted">{{ $loan->loaned_at->format('d/m/Y') }} {{ $loan->returned_at ? '→ ' . $loan->returned_at->format('d/m/Y') : '' }}</small>
                            </div>
                            @if(!$loan->returned_at)
                                <form method="POST" action="{{ route('admin.equipment.return', $loan) }}">@csrf <button class="btn btn-sm btn-outline-primary">{{ __('Return') }}</button></form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-layout>
