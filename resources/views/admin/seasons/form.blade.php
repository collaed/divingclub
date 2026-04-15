<x-admin-layout :title="__('New Season')">
    <h4 class="mb-4">{{ __('Create Season') }}</h4>
    <form method="POST" action="{{ route('admin.seasons.store') }}">
        @csrf
        <div class="row">
            <div class="col-md-2 mb-3">
                <label class="form-label">{{ __('Year') }}</label>
                <input type="number" name="year" id="seasonYear" class="form-control @error('year') is-invalid @enderror" value="{{ old('year', date('Y')) }}" required>
                @error('year') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">{{ __('Name') }}</label>
                <input type="text" name="name" id="seasonName" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Saison 2026-2027" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('Season Period') }} <small class="text-muted">({{ __('click twice to select range') }})</small></label>
                <input type="text" id="seasonRange" class="form-control" data-picker="daterange" data-range-start="#startDate" data-range-end="#endDate" placeholder="{{ __('Select start → end') }}" value="{{ old('start_date') && old('end_date') ? old('start_date').' to '.old('end_date') : '' }}">
                <input type="hidden" name="start_date" id="startDate" value="{{ old('start_date') }}">
                <input type="hidden" name="end_date" id="endDate" value="{{ old('end_date') }}">
                @error('start_date') <div class="text-danger small">{{ $message }}</div> @enderror
                @error('end_date') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">{{ __('Clone from previous season') }}</label>
            <select name="clone_from" class="form-select" style="max-width:300px">
                <option value="">{{ __('Start fresh') }}</option>
                @foreach($previousSeasons as $ps)
                    <option value="{{ $ps->id }}">{{ $ps->name }} ({{ $ps->year }})</option>
                @endforeach
            </select>
            <small class="text-muted">{{ __('Copies weekly patterns and holidays, adjusting dates to the new year.') }}</small>
        </div>
        <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
    </form>

    @push('scripts')
    <script>
    document.getElementById('seasonYear').addEventListener('change', function() {
        const y = parseInt(this.value);
        document.getElementById('seasonName').value = 'Saison ' + y + '-' + (y + 1).toString().slice(-2);
    });
    </script>
    @endpush
</x-admin-layout>
