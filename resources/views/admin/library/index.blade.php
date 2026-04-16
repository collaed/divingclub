<x-admin-layout :title="__('Document Library')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">@icon('📁') {{ __('Document Library') }}</h4>
        <span class="text-muted small">{{ \App\Models\LibraryFile::count() }} {{ __('files') }}</span>
    </div>

    {{-- Search bar --}}
    <form method="GET" action="{{ route('admin.library.index') }}" class="mb-3 d-flex gap-2" style="max-width:400px">
        <input type="text" name="search" data-instant-search="table-library" class="form-control form-control-sm" placeholder="{{ __('Search files by name or description…') }}" value="{{ $search ?? '' }}">
        <button class="btn btn-sm btn-outline-primary">{{ __('Search') }}</button>
        @if($search)
            <a href="{{ route('admin.library.index') }}" class="btn btn-sm btn-outline-secondary">✕</a>
        @endif
    </form>

    @if($search)
        <div class="alert alert-info py-2 mb-3">{{ __('Results for') }} "<strong>{{ $search }}</strong>" — {{ $files->count() }} {{ __('found across all folders') }}</div>
    @endif

    <div class="row">
        {{-- Folder tree sidebar --}}
        <div class="col-md-3">
            <div class="card dc-card mb-3">
                <div class="card-header py-2">{{ __('Folders') }}</div>
                <div style="max-height:500px;overflow-y:auto">
                    <a href="{{ route('admin.library.index', ['folder' => '/']) }}" class="list-group-item list-group-item-action py-1 border-0 {{ $folder === '/' ? 'active' : '' }}" style="font-size:13px">📁 {{ __('Root') }}</a>
                    @foreach($folders as $f)
                        @if($f !== '/')
                        @php
                            $depth = substr_count(trim($f, '/'), '/');
                            $isActive = $folder === $f;
                            $isAncestor = str_starts_with($folder . '/', $f . '/');
                            $parentPath = dirname($f);
                        @endphp
                        <a href="{{ route('admin.library.index', ['folder' => $f]) }}"
                           class="list-group-item list-group-item-action py-1 border-0 tree-item {{ $isActive ? 'active' : '' }}"
                           data-depth="{{ $depth }}"
                           data-parent="{{ $parentPath }}"
                           data-path="{{ $f }}"
                           style="padding-left:{{ 8 + $depth * 16 }}px;font-size:13px;{{ $depth > 0 && !$isAncestor && !$isActive ? 'display:none' : '' }}">
                            <span class="tree-arrow" style="display:inline-block;width:14px;font-size:10px;cursor:pointer">{{ $depth === 0 ? '▼' : '▶' }}</span>
                            {{ $isActive ? '📂' : '📁' }} {{ basename($f) }}
                        </a>
                        @endif
                    @endforeach
                </div>
                <script>
                document.querySelectorAll('.tree-arrow').forEach(function(arrow) {
                    arrow.addEventListener('click', function(e) {
                        e.preventDefault(); e.stopPropagation();
                        var item = this.closest('.tree-item');
                        var path = item.dataset.path;
                        var open = this.textContent.trim() === '▼';
                        this.textContent = open ? '▶' : '▼';
                        document.querySelectorAll('.tree-item').forEach(function(child) {
                            if (child.dataset.parent === path) {
                                child.style.display = open ? 'none' : '';
                                if (open) {
                                    // Also collapse children
                                    var childArrow = child.querySelector('.tree-arrow');
                                    if (childArrow) childArrow.textContent = '▶';
                                    document.querySelectorAll('.tree-item[data-parent="'+child.dataset.path+'"]').forEach(function(gc) { gc.style.display = 'none'; });
                                }
                            }
                        });
                    });
                });
                </script>
            </div>
            <form method="POST" action="{{ route('admin.library.create-folder') }}" class="card dc-card p-2">
                @csrf
                <div class="input-group input-group-sm">
                    <input type="text" name="folder" class="form-control" placeholder="{{ __('New folder path') }}" value="{{ $folder === '/' ? '/' : $folder . '/' }}">
                    <button class="btn btn-outline-primary">{{ __('Go') }}</button>
                </div>
            </form>
        </div>

        {{-- File list + upload --}}
        <div class="col-md-9">
            {{-- Dropzone upload --}}
            @if($folder)
            <div class="card dc-card mb-3">
                <div class="card-body py-2">
                    <form method="POST" action="{{ route('admin.library.upload') }}" enctype="multipart/form-data" id="uploadForm">
                        @csrf
                        <input type="hidden" name="folder" value="{{ $folder }}">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <div id="dropArea" style="border:2px dashed #ccc;border-radius:8px;padding:16px;text-align:center;cursor:pointer;transition:border-color 0.2s" onclick="document.getElementById('fileInput').click()">
                                    <span style="font-size:24px">📎</span><br>
                                    <small class="text-muted">{{ __('Drop files here or click to browse') }}</small>
                                    <input type="file" name="files[]" id="fileInput" multiple required style="display:none" onchange="updateDropLabel(this)">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="description" class="form-control form-control-sm" placeholder="{{ __('Description (optional)') }}">
                            </div>
                            <div class="col-md-2">
                                <select name="visibility" class="form-select form-select-sm">
                                    <option value="public">🌍 {{ __('Public') }}</option>
                                    <option value="members" selected>👥 {{ __('Members') }}</option>
                                    <option value="instructors">🎓 {{ __('Instructors') }}</option>
                                    <option value="bureau">🔒 {{ __('Bureau') }}</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary btn-sm w-100">{{ __('Upload') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            @endif

            {{-- Bulk actions --}}
            <div class="d-flex justify-content-between align-items-center mb-2" id="bulkBar" style="display:none!important">
                <small class="text-muted"><span id="selectedCount">0</span> {{ __('selected') }}</small>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary" onclick="downloadSelected()">📥 {{ __('Download ZIP') }}</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">{{ __('Deselect all') }}</button>
                </div>
            </div>

            {{-- File table --}}
            <div class="card dc-card">
                <div class="card-header py-2 d-flex justify-content-between">
                    <span>@icon('📂') {{ $folder ?? __('All folders') }}</span>
                    <small class="text-muted">{{ $files->count() }} {{ __('files') }}</small>
                </div>
                @if($files->isEmpty())
                    <div class="card-body text-muted text-center py-4">{{ __('No files in this folder.') }}</div>
                @else
                    {{-- Bulk action bar --}}
                    <div id="bulkBar" class="alert alert-primary py-2 mb-2 d-flex align-items-center gap-2" style="display:none!important">
                        <strong id="bulkCount">0</strong> {{ __('selected') }}
                        <button type="button" class="btn btn-sm btn-danger ms-auto" onclick="bulkDelete()">@icon('🗑') {{ __('Delete selected') }}</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false);document.querySelector('thead input[type=checkbox]').checked=false">{{ __('Clear') }}</button>
                    </div>
                    <div class="table-responsive">
                        <table id="table-library" class="table table-sm table-hover mb-0">
                            <thead><tr>
                                <th style="width:30px"><input type="checkbox" onchange="toggleAll(this.checked)" title="{{ __('Select all') }}"></th>
                                <th style="width:44px"></th>
                                <th>{{ __('Name') }}</th>
                                @if($search)<th>{{ __('Folder') }}</th>@endif
                                <th>{{ __('Size') }}</th>
                                <th>{{ __('Visibility') }}</th>
                                <th>{{ __('Uploaded') }}</th>
                                <th></th>
                            </tr></thead>
                            <tbody>
                            @foreach($files as $f)
                                <tr>
                                    <td><input type="checkbox" class="file-check" value="{{ $f->id }}" onchange="updateBulkBar()"></td>
                                    <td>
                                        @if($f->hasThumb())
                                            <a href="{{ route('admin.library.download', $f) }}" class="preview-link" data-type="{{ $f->mime_type }}" data-name="{{ $f->original_name }}">
                                                <img src="{{ route('admin.library.thumb', $f) }}" alt="" style="max-width:40px;max-height:40px;border-radius:3px;cursor:pointer" loading="lazy">
                                            </a>
                                        @else
                                            @php $ext = pathinfo($f->original_name, PATHINFO_EXTENSION); @endphp
                                            @if(in_array($ext, ['pdf'])) <a href="{{ route('admin.library.download', $f) }}" class="preview-link text-decoration-none" data-type="application/pdf">📄</a>
                                            @elseif(in_array($ext, ['doc','docx'])) 📝
                                            @elseif(in_array($ext, ['xls','xlsx'])) 📊
                                            @elseif(in_array($ext, ['pptx','ppt'])) 📊
                                            @elseif(in_array($ext, ['mp4','mov'])) 🎬
                                            @else 📎
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.library.download', $f) }}">{{ $f->original_name }}</a>
                                        @if($f->description) <br><small class="text-muted">{{ $f->description }}</small> @endif
                                    </td>
                                    @if($search)<td class="small text-muted">{{ $f->folder }}</td>@endif
                                    <td class="text-nowrap small">{{ $f->humanSize() }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.library.update', $f) }}" class="d-inline">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="folder" value="{{ $f->folder }}">
                                            <select name="visibility" class="form-select form-select-sm py-0" style="font-size:0.7rem;width:auto" onchange="this.form.submit()">
                                                @foreach(['public' => '🌍', 'members' => '👥', 'instructors' => '🎓', 'bureau' => '🔒'] as $v => $icon)
                                                    <option value="{{ $v }}" {{ $f->visibility === $v ? 'selected' : '' }}>{{ $icon }} {{ ucfirst($v) }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    </td>
                                    <td class="text-nowrap small">{{ $f->created_at->format('d/m/Y') }}<br>{{ $f->uploader?->name }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.library.destroy', $f) }}" class="d-inline" data-confirm="{{ __('Delete?') }}" data-confirm-style="danger" data-confirm-btn="{{ __('Delete') }}">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger py-0">✕</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

@push('scripts')
<script>

function toggleAll(checked) {
    document.querySelectorAll('.file-check').forEach(cb => cb.checked = checked);
    updateBulkBar();
}
function updateBulkBar() {
    var n = document.querySelectorAll('.file-check:checked').length;
    var bar = document.getElementById('bulkBar');
    bar.style.display = n > 0 ? 'flex' : 'none';
    bar.style.setProperty('display', n > 0 ? 'flex' : 'none', 'important');
    document.getElementById('bulkCount').textContent = n;
}
function bulkDelete() {
    var ids = Array.from(document.querySelectorAll('.file-check:checked')).map(cb => cb.value);
    if (!ids.length) return;
    dcConfirm('Delete ' + ids.length + ' files?', '{{ __("Delete") }}', 'danger', function(ok) {
        if (!ok) return;
        fetch('{{ route("admin.library.bulk-delete") }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({ids: ids})
        }).then(r => { if(r.ok) location.reload(); });
    });
}

// Drag-and-drop upload
const dropArea = document.getElementById('dropArea');
if (dropArea) {
    ['dragenter','dragover'].forEach(e => dropArea.addEventListener(e, ev => { ev.preventDefault(); dropArea.style.borderColor = '#0077be'; }));
    ['dragleave','drop'].forEach(e => dropArea.addEventListener(e, ev => { ev.preventDefault(); dropArea.style.borderColor = '#ccc'; }));
    dropArea.addEventListener('drop', ev => {
        document.getElementById('fileInput').files = ev.dataTransfer.files;
        updateDropLabel(document.getElementById('fileInput'));
    });
}

function updateDropLabel(input) {
    const n = input.files.length;
    dropArea.querySelector('small').textContent = n + ' {{ __("file(s) selected") }}';
    dropArea.style.borderColor = '#28a745';
}

// Bulk select
function toggleAll(checked) {
    document.querySelectorAll('.file-check').forEach(cb => cb.checked = checked);
    updateBulkBar();
}

function updateBulkBar() {
    const checked = document.querySelectorAll('.file-check:checked').length;
    const bar = document.getElementById('bulkBar');
    bar.style.display = checked > 0 ? 'flex' : 'none';
    bar.style.setProperty('display', checked > 0 ? 'flex' : 'none', 'important');
    document.getElementById('selectedCount').textContent = checked;
}

function downloadSelected() {
    const ids = [...document.querySelectorAll('.file-check:checked')].map(cb => cb.value).join(',');
    if (ids) window.location = '{{ route("admin.library.download-zip") }}?ids=' + ids;
}

// Image & PDF preview (inline lightbox)
document.querySelectorAll('.preview-link').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:pointer;padding:20px';
        if (this.dataset.type?.startsWith('image/')) {
            overlay.innerHTML = '<img src="' + this.href + '" style="max-width:90vw;max-height:90vh;border-radius:8px;box-shadow:0 0 40px rgba(0,0,0,0.5)">';
        } else if (this.dataset.type === 'application/pdf') {
            overlay.innerHTML = '<iframe src="' + this.href + '" style="width:90vw;height:90vh;border:none;border-radius:8px;background:white"></iframe>';
        }
        overlay.addEventListener('click', ev => { if (ev.target === overlay) overlay.remove(); });
        document.body.appendChild(overlay);
    });
});
</script>
@endpush
</x-admin-layout>
