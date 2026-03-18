<x-layout :title="__('New Season')">
    <h4 class="mb-4">{{ __('Create Season') }}</h4>
    <form method="POST" action="{{ route('admin.seasons.store') }}">
        @csrf
        <div class="row">
            <div class="col-md-2 mb-3">
                <label class="form-label">{{ __('Year') }}</label>
                <input type="number" name="year" class="form-control @error('year') is-invalid @enderror" value="{{ old('year', date('Y')) }}" required>
                @error('year') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">{{ __('Name') }}</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Season 2026-2027" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">{{ __('Start Date') }}</label>
                <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date') }}" required>
                @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">{{ __('End Date') }}</label>
                <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date') }}" required>
                @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
</x-layout>
