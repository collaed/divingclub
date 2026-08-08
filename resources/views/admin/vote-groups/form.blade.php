<x-admin-layout :title="__('Create Vote Group')">
    <h4 class="mb-4">🗳️ {{ __('Create Vote Group') }}</h4>
    <div class="card dc-card"><div class="card-body">
        <form method="POST" action="{{ route('admin.vote-groups.store') }}" id="voteGroupForm">
            @csrf
            <div class="row g-3 mb-4">
                <div class="col-md-8"><label class="form-label">{{ __('Group Title') }} *</label><input type="text" name="title" value="{{ old('title') }}" class="form-control" required placeholder="{{ __('e.g. Assemblée Générale 2026') }}"></div>
                <div class="col-12"><label class="form-label">{{ __('Description') }}</label><textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea></div>
                <div class="col-md-6"><label class="form-label">{{ __('Opens At') }}</label><input type="datetime-local" name="opens_at" value="{{ old('opens_at') }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">{{ __('Closes At') }}</label><input type="datetime-local" name="closes_at" value="{{ old('closes_at') }}" class="form-control"></div>
            </div>

            <hr>
            <h5 class="mb-3">{{ __('Questions') }}</h5>

            <div id="questions-container">
                <div class="question-block card mb-3 p-3" data-index="0">
                    <div class="row g-2 mb-2">
                        <div class="col-md-7"><label class="form-label fw-bold">{{ __('Question') }} 1 *</label><input type="text" name="questions[0][title]" class="form-control" required placeholder="{{ __('e.g. Approve accounts') }}"></div>
                        <div class="col-md-3"><label class="form-label">{{ __('Mode') }}</label>
                            <select name="questions[0][mode]" class="form-select question-mode">
                                <option value="simple">{{ __('Simple (Yes/No)') }}</option>
                                <option value="election">{{ __('Election (pick N)') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2"><label class="form-label">{{ __('Seats') }}</label><input type="number" name="questions[0][num_positions]" class="form-control" value="1" min="1" max="20"></div>
                    </div>
                    <div class="mb-2"><label class="form-label small">{{ __('Description (optional, supports HTML & links)') }}</label><textarea name="questions[0][description]" class="form-control form-control-sm" rows="2" placeholder="{{ __('e.g. See treasurer report: https://...') }}"></textarea></div>
                    <div class="options-list">
                        <label class="form-label small">{{ __('Options') }}</label>
                        <div class="input-group mb-1"><input type="text" name="questions[0][options][]" class="form-control form-control-sm" required placeholder="{{ __('Option 1') }}"><button type="button" class="btn btn-outline-danger btn-sm d-none" data-remove-opt>✕</button></div>
                        <div class="input-group mb-1"><input type="text" name="questions[0][options][]" class="form-control form-control-sm" required placeholder="{{ __('Option 2') }}"><button type="button" class="btn btn-outline-danger btn-sm d-none" data-remove-opt>✕</button></div>
                    </div>
                    <div class="mt-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-add-opt>+ {{ __('Option') }}</button>
                        <button type="button" class="btn btn-sm btn-outline-info ms-2" data-preset-yesno>{{ __('Yes/No/Abstain') }}</button>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="addQuestion">+ {{ __('Add Question') }}</button>

            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif

            <div class="mt-3"><button class="btn btn-primary">{{ __('Create Vote Group') }}</button></div>
        </form>
    </div></div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('questions-container');
        let qIndex = 1;

        document.getElementById('addQuestion').addEventListener('click', function() {
            const block = document.createElement('div');
            block.className = 'question-block card mb-3 p-3';
            block.dataset.index = qIndex;
            block.innerHTML = `
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="row g-2 flex-grow-1">
                        <div class="col-md-7"><label class="form-label fw-bold">{{ __('Question') }} ${qIndex + 1} *</label><input type="text" name="questions[${qIndex}][title]" class="form-control" required></div>
                        <div class="col-md-3"><label class="form-label">{{ __('Mode') }}</label><select name="questions[${qIndex}][mode]" class="form-select question-mode"><option value="simple">{{ __('Simple') }}</option><option value="election">{{ __('Election') }}</option></select></div>
                        <div class="col-md-2"><label class="form-label">{{ __('Seats') }}</label><input type="number" name="questions[${qIndex}][num_positions]" class="form-control" value="1" min="1" max="20"></div>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm ms-2" data-remove-question>✕</button>
                </div>
                <div class="mb-2"><label class="form-label small">{{ __('Description (optional)') }}</label><textarea name="questions[${qIndex}][description]" class="form-control form-control-sm" rows="2"></textarea></div>
                <div class="options-list">
                    <label class="form-label small">{{ __('Options') }}</label>
                    <div class="input-group mb-1"><input type="text" name="questions[${qIndex}][options][]" class="form-control form-control-sm" required placeholder="{{ __('Option 1') }}"><button type="button" class="btn btn-outline-danger btn-sm d-none" data-remove-opt>✕</button></div>
                    <div class="input-group mb-1"><input type="text" name="questions[${qIndex}][options][]" class="form-control form-control-sm" required placeholder="{{ __('Option 2') }}"><button type="button" class="btn btn-outline-danger btn-sm d-none" data-remove-opt>✕</button></div>
                </div>
                <div class="mt-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-add-opt>+ {{ __('Option') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-info ms-2" data-preset-yesno>{{ __('Yes/No/Abstain') }}</button>
                </div>`;
            container.appendChild(block);
            qIndex++;
        });

        container.addEventListener('click', function(e) {
            const target = e.target;
            if (target.hasAttribute('data-remove-question')) {
                target.closest('.question-block').remove();
            }
            if (target.hasAttribute('data-remove-opt')) {
                target.closest('.input-group').remove();
            }
            if (target.hasAttribute('data-add-opt')) {
                const block = target.closest('.question-block');
                const idx = block.dataset.index;
                const list = block.querySelector('.options-list');
                const count = list.querySelectorAll('input').length;
                if (count >= 10) return;
                const div = document.createElement('div');
                div.className = 'input-group mb-1';
                div.innerHTML = `<input type="text" name="questions[${idx}][options][]" class="form-control form-control-sm" placeholder="{{ __('Option') }} ${count + 1}"><button type="button" class="btn btn-outline-danger btn-sm" data-remove-opt>✕</button>`;
                list.appendChild(div);
            }
            if (target.hasAttribute('data-preset-yesno')) {
                const block = target.closest('.question-block');
                const idx = block.dataset.index;
                const list = block.querySelector('.options-list');
                list.innerHTML = `<label class="form-label small">{{ __('Options') }}</label>
                    <div class="input-group mb-1"><input type="text" name="questions[${idx}][options][]" class="form-control form-control-sm" value="{{ __('Yes') }}" required></div>
                    <div class="input-group mb-1"><input type="text" name="questions[${idx}][options][]" class="form-control form-control-sm" value="{{ __('No') }}" required></div>
                    <div class="input-group mb-1"><input type="text" name="questions[${idx}][options][]" class="form-control form-control-sm" value="{{ __('Abstain') }}"><button type="button" class="btn btn-outline-danger btn-sm" data-remove-opt>✕</button></div>`;
            }
        });
    });
    </script>
</x-admin-layout>
