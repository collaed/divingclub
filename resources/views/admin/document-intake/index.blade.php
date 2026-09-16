<x-admin-layout :title="__('Document Intake')">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">@icon('📥') {{ __('Document Intake') }}</h4>
    </div>
    <p class="text-muted small">{{ __('Drop federation licence scans and bank statements here — each one is read automatically, the type is detected, and it\'s routed to the right place. If a guess is wrong, correct it below and it will be reprocessed.') }}</p>

    @if(session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
    @endif
    @error('files.*')
        <div class="alert alert-danger py-2 small">{{ $message }}</div>
    @enderror

    <div class="card dc-card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.document-intake.store') }}" enctype="multipart/form-data" class="d-flex gap-2 align-items-end">
                @csrf
                <div class="flex-grow-1">
                    <div class="dc-dropzone rounded d-flex align-items-center justify-content-center gap-2 text-muted small" style="border: 2px dashed #ccc;" tabindex="0" role="button">
                        <span>@icon('📥')</span>
                        <span class="dc-dropzone-label">{{ __('Drop licence scans or bank statements here, or click to browse') }}</span>
                        <input type="file" name="files[]" class="dc-dropzone-input visually-hidden" accept="application/pdf,image/jpeg,image/png" multiple required>
                    </div>
                </div>
                <button type="submit" class="btn btn-sm btn-primary flex-shrink-0">@icon('⬆️') {{ __('Upload') }}</button>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle table-sm">
            <thead>
                <tr>
                    <th>{{ __('File') }}</th>
                    <th>{{ __('Detected as') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Outcome') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($intakes as $intake)
                    <tr>
                        <td class="small">{{ $intake->original_filename }}</td>
                        <td class="small">
                            @if($intake->detected_type === 'licence_scan')
                                🪪 {{ __('Licence scan') }}@if($intake->federation) ({{ $intake->federation->acronym }})@endif
                            @elseif($intake->detected_type === 'bank_statement')
                                🏦 {{ __('Bank statement') }}
                            @else
                                <span class="text-muted">{{ __('Not yet determined') }}</span>
                            @endif
                            @if($intake->detection_reason)
                                <br><span class="text-muted" style="font-size:.75rem" title="{{ $intake->detection_reason }}">ℹ️ {{ Str::limit($intake->detection_reason, 60) }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ match($intake->status) { 'routed' => 'success', 'needs_review' => 'warning text-dark', 'failed' => 'danger', default => 'secondary' } }}">{{ __(ucfirst(str_replace('_', ' ', $intake->status))) }}</span>
                        </td>
                        <td class="small">
                            @if($intake->licenceScan)
                                <a href="{{ route('admin.licence-scans.index') }}">{{ __('View in Licence Scans') }}</a>
                            @elseif($intake->transactions_created !== null)
                                <a href="{{ route('admin.payments.reconciliation') }}">{{ __(':count transaction(s) → Reconciliation', ['count' => $intake->transactions_created]) }}</a>
                            @elseif($intake->error)
                                <span class="text-danger">{{ $intake->error }}</span>
                            @endif
                        </td>
                        <td>
                            @if($intake->status === 'needs_review')
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">{{ __('Set type') }}</button>
                                    <div class="dropdown-menu p-2" style="min-width:220px">
                                        <form method="POST" action="{{ route('admin.document-intake.reclassify', $intake) }}" class="mb-2">
                                            @csrf
                                            <input type="hidden" name="type" value="bank_statement">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">🏦 {{ __('This is a bank statement') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.document-intake.reclassify', $intake) }}" class="d-flex gap-1">
                                            @csrf
                                            <input type="hidden" name="type" value="licence_scan">
                                            <select name="federation_id" class="form-select form-select-sm" required>
                                                <option value="">{{ __('Federation…') }}</option>
                                                @foreach($federations as $federation)
                                                    <option value="{{ $federation->id }}">{{ $federation->acronym }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary flex-shrink-0">🪪</button>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No documents uploaded yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $intakes->links() }}

    <style>
        .dc-dropzone { min-height: 38px; padding: 6px 10px; cursor: pointer; transition: border-color .15s, color .15s; }
        .dc-dropzone.dc-dropzone-active { border-color: #0d6efd !important; color: #0d6efd; }
        .dc-dropzone.dc-dropzone-filled { border-color: #198754 !important; color: #198754; }
    </style>
    <script>
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
    </script>
</x-admin-layout>
