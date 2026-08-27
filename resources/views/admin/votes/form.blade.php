<x-admin-layout :title="__('New Vote')">
    <h4 class="mb-4">{{ __('Create Vote') }}</h4>
    <div class="card dc-card"><div class="card-body">
        <form method="POST" action="{{ route('admin.votes.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">{{ __('Title') }} *</label><input type="text" name="title" value="{{ old('title') }}" class="form-control @error('title') is-invalid @enderror" required>@error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="col-md-4"><label class="form-label">{{ __('Mode') }} *</label>
                    <select name="mode" id="voteMode" class="form-select @error('mode') is-invalid @enderror" required>
                        <option value="simple" @selected(old('mode', 'simple') === 'simple')>{{ __('Simple (changeable)') }}</option>
                        <option value="election" @selected(old('mode') === 'election')>{{ __('Election (anonymous, irreversible)') }}</option>
                    </select>
                    @error('mode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12"><label class="form-label">{{ __('Description') }}</label><textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="2">{{ old('description') }}</textarea>@error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="col-md-6"><label class="form-label">{{ __('Opens At') }}</label><input type="datetime-local" name="opens_at" value="{{ old('opens_at') }}" class="form-control @error('opens_at') is-invalid @enderror">@error('opens_at') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="col-md-6"><label class="form-label">{{ __('Closes At') }}</label><input type="datetime-local" name="closes_at" value="{{ old('closes_at') }}" class="form-control @error('closes_at') is-invalid @enderror">@error('closes_at') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="col-12">
                    <div class="form-check form-check-inline"><input type="hidden" name="allow_multiple" value="0"><input type="checkbox" name="allow_multiple" value="1" class="form-check-input" id="allowMultiple" @checked(old('allow_multiple'))><label class="form-check-label" for="allowMultiple">{{ __('Allow multiple selections') }}</label></div>
                    <div class="form-check form-check-inline"><input type="hidden" name="allow_change" value="0"><input type="checkbox" name="allow_change" value="1" class="form-check-input" id="allowChange" @checked(old('allow_change', true))><label class="form-check-label" for="allowChange">{{ __('Allow vote change') }}</label></div>
                    <div class="form-check form-check-inline"><input type="hidden" name="is_public" value="0"><input type="checkbox" name="is_public" value="1" class="form-check-input" id="isPublic" @checked(old('is_public'))><label class="form-check-label" for="isPublic">{{ __('Show results publicly') }}</label></div>
                </div>
                <div class="col-md-4"><label class="form-label">{{ __('Positions to fill') }}</label><input type="number" name="num_positions" id="numPositions" class="form-control @error('num_positions') is-invalid @enderror" value="{{ old('num_positions', 1) }}" min="1" max="20"><small class="text-muted">{{ __('For elections: how many seats. Voters select up to this many.') }}</small>@error('num_positions') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="col-md-4"><label class="form-label">{{ __('Min vote % to elect') }}</label><input type="number" name="min_vote_pct" class="form-control @error('min_vote_pct') is-invalid @enderror" value="{{ old('min_vote_pct', 50) }}" min="0" max="100"><small class="text-muted">{{ __('Only candidates with ≥ this % are elected.') }}</small>@error('min_vote_pct') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="col-12">
                    <label class="form-label">{{ __('Options') }} *</label>
                    <div class="mb-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="yesno">{{ __('Preset: Yes / No / Abstain') }}</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="approve">{{ __('Preset: Approve / Reject / Abstain') }}</button>
                    </div>
                    <div id="options-container">
                        @php $oldOptions = old('options', ['', '']); @endphp
                        @foreach($oldOptions as $i => $val)
                        <div class="input-group mb-1">
                            <input type="text" name="options[]" class="form-control @error("options.$i") is-invalid @enderror" value="{{ $val }}" placeholder="{{ __('Option') }} {{ $i + 1 }}" {{ $i < 2 ? 'required' : '' }}>
                            @if($i >= 2)
                            <button type="button" class="btn btn-outline-danger btn-sm" data-remove-option>✕</button>
                            @endif
                            @error("options.$i") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="addOption">+ {{ __('Add option') }}</button>
                    <small class="text-muted d-block mt-1">{{ __('Maximum 10 options.') }}</small>
                    @error('options') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>
            <button class="btn btn-primary mt-3">{{ __('Create') }}</button>
        </form>
    </div></div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('options-container');
        const addBtn = document.getElementById('addOption');
        const maxOptions = 10;

        addBtn.addEventListener('click', function() {
            const count = container.querySelectorAll('input[name="options[]"]').length;
            if (count >= maxOptions) return;
            const div = document.createElement('div');
            div.className = 'input-group mb-1';
            div.innerHTML = '<input type="text" name="options[]" class="form-control" placeholder="{{ __("Option") }} ' + (count + 1) + '">'
                + '<button type="button" class="btn btn-outline-danger btn-sm" data-remove-option>✕</button>';
            container.appendChild(div);
            if (count + 1 >= maxOptions) addBtn.disabled = true;
        });

        container.addEventListener('click', function(e) {
            if (e.target.hasAttribute('data-remove-option')) {
                e.target.closest('.input-group').remove();
                addBtn.disabled = false;
            }
        });

        document.querySelectorAll('[data-preset]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const presets = {
                    'yesno': ['{{ __("Yes") }}', '{{ __("No") }}', '{{ __("Abstain") }}'],
                    'approve': ['{{ __("Approve") }}', '{{ __("Reject") }}', '{{ __("Abstain") }}']
                };
                const opts = presets[this.dataset.preset];
                container.innerHTML = '';
                opts.forEach(function(label, i) {
                    const div = document.createElement('div');
                    div.className = 'input-group mb-1';
                    div.innerHTML = '<input type="text" name="options[]" class="form-control" value="' + label + '" required>'
                        + (i >= 2 ? '<button type="button" class="btn btn-outline-danger btn-sm" data-remove-option>✕</button>' : '');
                    container.appendChild(div);
                });
                addBtn.disabled = false;
            });
        });

        // Election mode: auto-check allow_multiple and set positions
        document.getElementById('voteMode').addEventListener('change', function() {
            if (this.value === 'election') {
                document.getElementById('allowMultiple').checked = true;
                document.getElementById('allowChange').checked = false;
            }
        });
    });
    </script>
</x-admin-layout>
