<x-admin-layout :title="__('New Vote')">
    <h4 class="mb-4">{{ __('Create Vote') }}</h4>
    <div class="card dc-card"><div class="card-body">
        <form method="POST" action="{{ route('admin.votes.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">{{ __('Title') }} *</label><input type="text" name="title" value="{{ old('title') }}" class="form-control @error('title') is-invalid @enderror" required>@error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="col-md-4"><label class="form-label">{{ __('Mode') }} *</label>
                    <select name="mode" class="form-select @error('mode') is-invalid @enderror" required>
                        <option value="simple" @selected(old('mode', 'simple') === 'simple')>{{ __('Simple (changeable)') }}</option>
                        <option value="election" @selected(old('mode') === 'election')>{{ __('Election (anonymous, irreversible)') }}</option>
                    </select>
                    @error('mode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12"><label class="form-label">{{ __('Description') }}</label><textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="2">{{ old('description') }}</textarea>@error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="col-md-6"><label class="form-label">{{ __('Opens At') }}</label><input type="datetime-local" name="opens_at" value="{{ old('opens_at') }}" class="form-control @error('opens_at') is-invalid @enderror">@error('opens_at') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="col-md-6"><label class="form-label">{{ __('Closes At') }}</label><input type="datetime-local" name="closes_at" value="{{ old('closes_at') }}" class="form-control @error('closes_at') is-invalid @enderror">@error('closes_at') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="col-12">
                    <div class="form-check form-check-inline"><input type="hidden" name="allow_multiple" value="0"><input type="checkbox" name="allow_multiple" value="1" class="form-check-input" @checked(old('allow_multiple'))><label class="form-check-label">{{ __('Allow multiple selections') }}</label></div>
                    <div class="form-check form-check-inline"><input type="hidden" name="allow_change" value="0"><input type="checkbox" name="allow_change" value="1" class="form-check-input" @checked(old('allow_change', true))><label class="form-check-label">{{ __('Allow vote change') }}</label></div>
                    <div class="form-check form-check-inline"><input type="hidden" name="is_public" value="0"><input type="checkbox" name="is_public" value="1" class="form-check-input" @checked(old('is_public'))><label class="form-check-label">{{ __('Show results publicly') }}</label></div>
                </div>
                <div class="col-md-4"><label class="form-label">{{ __('Positions to fill') }}</label><input type="number" name="num_positions" class="form-control @error('num_positions') is-invalid @enderror" value="{{ old('num_positions', 1) }}" min="1" max="20"><small class="text-muted">{{ __('For elections: how many seats (e.g. 6 for bureau). Voters select up to this many candidates.') }}</small>@error('num_positions') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="col-md-4"><label class="form-label">{{ __('Min vote % to elect') }}</label><input type="number" name="min_vote_pct" class="form-control @error('min_vote_pct') is-invalid @enderror" value="{{ old('min_vote_pct', 50) }}" min="0" max="100"><small class="text-muted">{{ __('If fewer candidates than positions, only those with ≥ this % are elected.') }}</small>@error('min_vote_pct') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="col-12">
                    <label class="form-label">{{ __('Options') }} * ({{ __('one per line, minimum 2') }})</label>
                    <div id="options">
                        <input type="text" name="options[]" class="form-control mb-1 @error('options.0') is-invalid @enderror" value="{{ old('options.0') }}" placeholder="{{ __('Option 1') }}" required>
                        @error('options.0') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <input type="text" name="options[]" class="form-control mb-1 @error('options.1') is-invalid @enderror" value="{{ old('options.1') }}" placeholder="{{ __('Option 2') }}" required>
                        @error('options.1') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <input type="text" name="options[]" class="form-control mb-1 @error('options.2') is-invalid @enderror" value="{{ old('options.2') }}" placeholder="{{ __('Option 3 (optional)') }}">
                        @error('options.2') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    @error('options') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>
            <button class="btn btn-primary mt-3">{{ __('Create') }}</button>
        </form>
    </div></div>
</x-admin-layout>
