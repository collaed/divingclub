<x-admin-layout :title="__('Licence Scans')">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">@icon('🪪') {{ __('Licence Scans') }}</h4>
    </div>
    <p class="text-muted small">{{ __('Dump a batch of licence card scans; each one is read automatically and applied straight to the member when the name matches exactly one person. Anything else lands below for you to assign by hand.') }}</p>

    @if(session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
    @endif
    @error('files.*')
        <div class="alert alert-danger py-2 small">{{ $message }}</div>
    @enderror

    <div class="card dc-card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.licence-scans.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf
                <div class="col-auto">
                    <label class="form-label small mb-1">{{ __('Federation') }}</label>
                    <select name="federation_id" class="form-select form-select-sm" required>
                        @foreach($federations as $federation)
                            <option value="{{ $federation->id }}">{{ $federation->acronym }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col">
                    <label class="form-label small mb-1">{{ __('Scans (PDF or image, one per member)') }}</label>
                    <div class="dc-dropzone rounded d-flex align-items-center justify-content-center gap-2 text-muted small" style="border: 2px dashed #ccc;" tabindex="0" role="button">
                        <span>@icon('📥')</span>
                        <span class="dc-dropzone-label">{{ __('Drop files here, or click to browse') }}</span>
                        <input type="file" name="files[]" class="dc-dropzone-input visually-hidden" accept="application/pdf,image/jpeg,image/png" multiple required>
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">@icon('⬆️') {{ __('Upload & process') }}</button>
                </div>
            </form>
        </div>
    </div>

    @if($needsReview->isEmpty())
        <div class="card dc-card"><div class="card-body text-center py-5 text-muted">{{ __('Nothing waiting for review.') }}</div></div>
    @else
        <h6>{{ __('Needs review') }}</h6>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Scan') }}</th>
                        <th>{{ __('Federation') }}</th>
                        <th>{{ __('Extracted') }}</th>
                        <th style="min-width:280px">{{ __('Assign to') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($needsReview as $scan)
                        <tr>
                            <td>
                                @if($scan->image_path)
                                    <a href="{{ route('admin.licence-scans.image', $scan) }}" target="_blank" rel="noopener">
                                        <img src="{{ route('admin.licence-scans.image', $scan) }}" alt="" style="max-width:80px; max-height:52px; object-fit:cover; border-radius:4px;">
                                    </a>
                                @else
                                    <span class="text-muted small">{{ __('No preview') }}</span>
                                @endif
                                <div class="small text-muted">{{ $scan->original_filename }}</div>
                                @if($scan->extraction_error)
                                    <div class="small text-danger">{{ $scan->extraction_error }}</div>
                                @endif
                            </td>
                            <td>{{ $scan->federation->acronym }}</td>
                            <td class="small">
                                {{ $scan->extracted_name ?? __('(no name read)') }}<br>
                                @if($scan->extracted_number) #{{ $scan->extracted_number }} @endif
                                @if($scan->extracted_year) · {{ $scan->extracted_year }} @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.licence-scans.assign', $scan) }}" class="d-flex flex-wrap gap-1">
                                    @csrf
                                    <input type="text" list="dc-members-list" class="form-control form-control-sm dc-member-picker" placeholder="{{ __('Search member…') }}" style="min-width:180px" value="{{ $scan->extracted_name }}">
                                    <input type="hidden" name="user_id" class="dc-member-id">
                                    <input type="text" name="licence_number" class="form-control form-control-sm" style="width:100px" placeholder="{{ __('Number') }}" value="{{ $scan->extracted_number }}">
                                    <input type="text" name="year" class="form-control form-control-sm" style="width:80px" placeholder="{{ __('Year') }}" value="{{ $scan->extracted_year }}">
                                    <button type="submit" class="btn btn-sm btn-success">@icon('✅') {{ __('Assign') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.licence-scans.destroy', $scan) }}" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger mt-1">@icon('🗑️') {{ __('Discard') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $needsReview->links() }}
    @endif

    <datalist id="dc-members-list">
        @foreach($members as $member)
            <option data-id="{{ $member['id'] }}" value="{{ $member['name'] }}"></option>
        @endforeach
    </datalist>

    <style>
        .dc-dropzone { min-height: 38px; padding: 6px 10px; cursor: pointer; transition: border-color .15s, color .15s; }
        .dc-dropzone.dc-dropzone-active { border-color: #0d6efd !important; color: #0d6efd; }
        .dc-dropzone.dc-dropzone-filled { border-color: #198754 !important; color: #198754; }
    </style>

    <script>
        // No inline handlers, event delegation per project JS convention.
        document.querySelectorAll('.dc-dropzone').forEach(function (zone) {
            var input = zone.querySelector('.dc-dropzone-input');
            var label = zone.querySelector('.dc-dropzone-label');
            var defaultLabel = label.textContent;

            var updateLabel = function () {
                var n = input.files.length;
                zone.classList.toggle('dc-dropzone-filled', n > 0);
                label.textContent = n > 0 ? n + ' ' + '{{ __('file(s) selected') }}' : defaultLabel;
            };

            zone.addEventListener('click', function () { input.click(); });
            zone.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
            });
            input.addEventListener('change', updateLabel);

            ['dragenter', 'dragover'].forEach(function (evt) {
                zone.addEventListener(evt, function (e) { e.preventDefault(); zone.classList.add('dc-dropzone-active'); });
            });
            ['dragleave', 'drop'].forEach(function (evt) {
                zone.addEventListener(evt, function (e) { e.preventDefault(); zone.classList.remove('dc-dropzone-active'); });
            });
            zone.addEventListener('drop', function (e) {
                input.files = e.dataTransfer.files;
                updateLabel();
            });
        });

        // The datalist only carries names (browsers don't expose the option's
        // other attributes on match), so resolve the typed name back to a
        // user id by looking the option up again on change/blur.
        document.querySelectorAll('.dc-member-picker').forEach(function (input) {
            var hidden = input.closest('form').querySelector('.dc-member-id');
            var resolve = function () {
                var match = document.querySelector('#dc-members-list option[value="' + CSS.escape(input.value) + '"]');
                hidden.value = match ? match.dataset.id : '';
            };
            input.addEventListener('input', resolve);
            resolve();
        });
    </script>
</x-admin-layout>
