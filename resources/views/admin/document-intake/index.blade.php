<x-admin-layout :title="__('Document Intake')">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">@icon('📥') {{ __('Document Intake') }}</h4>
    </div>
    <p class="text-muted small">{{ __('Drop federation licence scans, bank statements, or FFESSM brevets exports here — each one is read automatically (or sent straight to the matching drop area below) and routed to the right place. If a guess is wrong, correct it in the table and it will be reprocessed.') }}</p>

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

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card dc-card h-100">
                <div class="card-body">
                    <h6 class="small fw-bold">@icon('🪪') {{ __('Federation licence scans') }}</h6>
                    <form method="POST" action="{{ route('admin.document-intake.store') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="intake_type" value="licence_scan">
                        <div class="dc-dropzone rounded d-flex align-items-center justify-content-center gap-2 text-muted small mb-2" style="border: 2px dashed #ccc;" tabindex="0" role="button">
                            <span>@icon('🪪')</span>
                            <span class="dc-dropzone-label">{{ __('Drop licence card photos here') }}</span>
                            <input type="file" name="files[]" class="dc-dropzone-input visually-hidden" accept="image/jpeg,image/png" multiple required>
                        </div>
                        <div class="d-flex gap-2">
                            <select name="federation_id" class="form-select form-select-sm">
                                <option value="">{{ __('Unsure — review later') }}</option>
                                @foreach($federations as $federation)
                                    <option value="{{ $federation->id }}">{{ $federation->acronym }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary flex-shrink-0">@icon('⬆️') {{ __('Upload') }}</button>
                        </div>
                        <p class="text-muted small mt-1 mb-0">{{ __('Picking a federation routes the scan straight to OCR; leave it unset if cards are mixed and pick it later on the Licence Scans screen.') }}</p>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card dc-card h-100">
                <div class="card-body">
                    <h6 class="small fw-bold">@icon('🏅') {{ __('FFESSM brevets import') }}</h6>
                    <form method="POST" action="{{ route('admin.document-intake.store') }}" enctype="multipart/form-data" class="d-flex gap-2 align-items-end">
                        @csrf
                        <input type="hidden" name="intake_type" value="ffessm_brevet">
                        <div class="flex-grow-1">
                            <div class="dc-dropzone rounded d-flex align-items-center justify-content-center gap-2 text-muted small" style="border: 2px dashed #ccc;" tabindex="0" role="button">
                                <span>@icon('🏅')</span>
                                <span class="dc-dropzone-label">{{ __('Drop a "Liste des brevets" xlsx export here') }}</span>
                                <input type="file" name="files[]" class="dc-dropzone-input visually-hidden" accept=".xlsx" multiple required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary flex-shrink-0">@icon('⬆️') {{ __('Upload') }}</button>
                    </form>
                </div>
            </div>
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
                            @elseif($intake->detected_type === 'ffessm_brevet')
                                🏅 {{ __('FFESSM brevets import') }}
                                @if($intake->import_result)
                                    <br><span class="text-muted" style="font-size:.75rem">{{ __('Season :season — :certs cert(s), :licences licence(s)', ['season' => $intake->import_result['season'] ?? '—', 'certs' => ($intake->import_result['certs_created'] ?? 0) + ($intake->import_result['certs_updated'] ?? 0), 'licences' => $intake->import_result['licences_updated'] ?? 0]) }}</span>
                                @endif
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
                            @elseif($intake->detected_type === 'ffessm_brevet' && $intake->status === 'routed')
                                <span class="text-success">{{ __('Applied.') }}</span>
                            @endif
                        </td>
                        <td>
                            @if($intake->status === 'needs_review' && $intake->detected_type !== 'ffessm_brevet')
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
                            @elseif($intake->detected_type === 'ffessm_brevet' && in_array($intake->status, ['needs_review', 'failed'], true))
                                <form method="POST" action="{{ route('admin.document-intake.reclassify', $intake) }}">
                                    @csrf
                                    <input type="hidden" name="type" value="ffessm_brevet">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">🔄 {{ __('Retry') }}</button>
                                </form>
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
