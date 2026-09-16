<x-admin-layout :title="__('Document Library')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">@icon('📁') {{ __('Document Library') }}</h4>
        <span class="text-muted small">{{ \App\Models\LibraryFile::where('original_name', '!=', '.folder')->count() }} {{ __('files') }}</span>
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
                    <a href="{{ route('admin.library.index', ['folder' => '/']) }}" class="list-group-item list-group-item-action py-1 border-0 d-flex align-items-center {{ $folder === '/' ? 'active' : '' }}" style="font-size:13px">
                        <span class="tree-arrow-spacer" style="display:inline-block;width:20px"></span>
                        📁 {{ __('Root') }}
                        @if(($subfolderCounts['/'] ?? 0) || ($fileCounts['/'] ?? 0))
                            <span class="text-muted ms-1" style="font-size:11px" title="{{ __(':files file(s), :folders subfolder(s)', ['files' => $fileCounts['/'] ?? 0, 'folders' => $subfolderCounts['/'] ?? 0]) }}">({{ $fileCounts['/'] ?? 0 }}, {{ $subfolderCounts['/'] ?? 0 }})</span>
                        @endif
                    </a>
                    @foreach($folders as $f)
                        @if($f !== '/')
                        @php
                            $depth = substr_count(trim($f, '/'), '/');
                            $isActive = $folder === $f;
                            $isAncestor = str_starts_with($folder . '/', $f . '/');
                            $parentPath = dirname($f);
                            $hasChildren = ($subfolderCounts[$f] ?? 0) > 0;
                            // Open (▼) only along the path to the active folder — everything
                            // else starts collapsed, matching what's actually shown below it.
                            $isOpen = $isActive || $isAncestor;
                        @endphp
                        <a href="{{ route('admin.library.index', ['folder' => $f]) }}"
                           class="list-group-item list-group-item-action py-1 border-0 d-flex align-items-center tree-item {{ $isActive ? 'active' : '' }}"
                           data-depth="{{ $depth }}"
                           data-parent="{{ $parentPath }}"
                           data-path="{{ $f }}"
                           {{-- !important: this row's own d-flex class is display:flex !important
                                (Bootstrap's utility classes are all !important), which would
                                otherwise beat a plain inline display:none outright. --}}
                           style="padding-left:{{ 8 + $depth * 16 }}px;font-size:13px;{{ $depth > 0 && !$isAncestor && !$isActive ? 'display:none!important' : '' }}">
                            @if($hasChildren)
                                <span class="tree-arrow" style="display:inline-block;width:20px;font-size:16px;line-height:1;cursor:pointer">{{ $isOpen ? '▼' : '▶' }}</span>
                            @else
                                <span class="tree-arrow-spacer" style="display:inline-block;width:20px"></span>
                            @endif
                            {{ $isActive ? '📂' : '📁' }} {{ basename($f) }}
                            @if(($subfolderCounts[$f] ?? 0) || ($fileCounts[$f] ?? 0))
                                <span class="text-muted ms-1" style="font-size:11px" title="{{ __(':files file(s), :folders subfolder(s)', ['files' => $fileCounts[$f] ?? 0, 'folders' => $subfolderCounts[$f] ?? 0]) }}">({{ $fileCounts[$f] ?? 0 }}, {{ $subfolderCounts[$f] ?? 0 }})</span>
                            @endif
                        </a>
                        @endif
                    @endforeach
                </div>
                <script>
                // A tree-item's own d-flex class is display:flex !important
                // (every Bootstrap display utility is !important), so a plain
                // style.display = 'none' from JS loses to it exactly like the
                // server-rendered inline style would — must set with priority.
                function setTreeItemHidden(el, hidden) {
                    if (hidden) { el.style.setProperty('display', 'none', 'important'); }
                    else { el.style.removeProperty('display'); }
                }
                document.querySelectorAll('.tree-arrow').forEach(function(arrow) {
                    arrow.addEventListener('click', function(e) {
                        e.preventDefault(); e.stopPropagation();
                        var item = this.closest('.tree-item');
                        var path = item.dataset.path;
                        var open = this.textContent.trim() === '▼';
                        this.textContent = open ? '▶' : '▼';
                        document.querySelectorAll('.tree-item').forEach(function(child) {
                            if (child.dataset.parent === path) {
                                setTreeItemHidden(child, open);
                                if (open) {
                                    // Also collapse children
                                    var childArrow = child.querySelector('.tree-arrow');
                                    if (childArrow) childArrow.textContent = '▶';
                                    document.querySelectorAll('.tree-item[data-parent="'+child.dataset.path+'"]').forEach(function(gc) { setTreeItemHidden(gc, true); });
                                }
                            }
                        });
                    });
                });
                </script>
            </div>
            @if($folder)
            <form method="POST" action="{{ route('admin.library.create-folder') }}" class="card dc-card p-2">
                @csrf
                <input type="hidden" name="parent" value="{{ $folder }}">
                <label class="form-label small text-muted mb-1">{{ __('New subfolder of :folder', ['folder' => $folder]) }}</label>
                <div class="input-group input-group-sm">
                    <input type="text" name="name" class="form-control" placeholder="{{ __('Folder name') }}" pattern="[a-zA-Z0-9_\- ]+" required>
                    <button class="btn btn-outline-primary">{{ __('Create') }}</button>
                </div>
            </form>
            @endif
        </div>

        {{-- File list + upload --}}
        <div class="col-md-9">
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
                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" onclick="downloadSelected()">📥 {{ __('Download ZIP') }}</button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="bulkDelete()">@icon('🗑') {{ __('Delete selected') }}</button>
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
                                                <img src="{{ route('admin.library.thumb', $f) }}" alt="Decoration" style="max-width:40px;max-height:40px;border-radius:3px;cursor:pointer" loading="lazy">
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
                                    <td class="text-end text-nowrap">
                                        <div class="dropdown d-inline">
                                            <button class="btn btn-sm btn-outline-secondary py-0 px-1" data-bs-toggle="dropdown" title="{{ __('Actions') }}" aria-label="{{ __('Actions') }}">⋯</button>
                                            <ul class="dropdown-menu dropdown-menu-end" style="font-size:.85rem">
                                                <li><a class="dropdown-item" href="{{ route('admin.library.download', $f) }}">⬇ {{ __('Download') }}</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="renameFile({{ $f->id }}, '{{ addslashes($f->original_name) }}')">✏️ {{ __('Rename') }}</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="moveFile({{ $f->id }})">📁 {{ __('Move') }}</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" action="{{ route('admin.library.destroy', $f) }}" data-confirm="{{ __('Delete?') }}" data-confirm-style="danger" data-confirm-btn="{{ __('Delete') }}">
                                                        @csrf @method('DELETE')
                                                        <button class="dropdown-item text-danger">🗑 {{ __('Delete') }}</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Dropzone upload — deliberately last on the page: it's a
                 secondary action compared to browsing/managing files above. --}}
            @if($folder)
            <div class="card dc-card mt-3">
                <div class="card-header py-2">@icon('📎') {{ __('Upload to :folder', ['folder' => $folder]) }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.library.upload') }}" enctype="multipart/form-data" id="uploadForm">
                        @csrf
                        <input type="hidden" name="folder" value="{{ $folder }}">
                        <div id="dropArea" style="border:3px dashed #ccc;border-radius:10px;padding:40px 16px;text-align:center;cursor:pointer;transition:border-color 0.2s" onclick="document.getElementById('fileInput').click()">
                            <span style="font-size:40px">📎</span><br>
                            <span class="text-muted drop-label">{{ __('Drop files here or click to browse') }}</span>
                            <input type="file" name="files[]" id="fileInput" multiple required style="display:none" onchange="updateDropLabel(this)">
                        </div>
                        <div class="row g-2 align-items-end mt-2">
                            <div class="col-md-6">
                                <input type="text" name="description" class="form-control form-control-sm" placeholder="{{ __('Description (optional)') }}">
                            </div>
                            <div class="col-md-3">
                                <select name="visibility" class="form-select form-select-sm">
                                    <option value="public">🌍 {{ __('Public') }}</option>
                                    <option value="members" selected>👥 {{ __('Members') }}</option>
                                    <option value="instructors">🎓 {{ __('Instructors') }}</option>
                                    <option value="bureau">🔒 {{ __('Bureau') }}</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary btn-sm w-100">{{ __('Upload') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            @endif
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
    dropArea.querySelector('.drop-label').textContent = n + ' {{ __("file(s) selected") }}';
    dropArea.style.borderColor = '#28a745';
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

// Rename file
function renameFile(id, currentName) {
    const newName = prompt('{{ __("New name:") }}', currentName);
    if (!newName || newName === currentName) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/library/' + id + '/rename';
    form.innerHTML = '<input type="hidden" name="_token" value="' + document.querySelector('meta[name=csrf-token]').content + '">'
        + '<input type="hidden" name="name" value="' + newName.replace(/"/g, '&quot;') + '">';
    document.body.appendChild(form);
    form.submit();
}

// Move file
function moveFile(id) {
    const folders = @json($folders ?? []);
    let options = folders.map(f => '<option value="' + f + '">' + f + '</option>').join('');
    const dest = prompt('{{ __("Move to folder:") }}\n\n' + folders.join('\n'));
    if (!dest) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/library/' + id + '/move';
    form.innerHTML = '<input type="hidden" name="_token" value="' + document.querySelector('meta[name=csrf-token]').content + '">'
        + '<input type="hidden" name="destination" value="' + dest.replace(/"/g, '&quot;') + '">';
    document.body.appendChild(form);
    form.submit();
}
</script>
@endpush
</x-admin-layout>
