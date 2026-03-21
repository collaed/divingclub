{{-- Document browser with role-based visibility, upload & folder management | ClubCEP.eu --}}
<x-layout :title="__('Documents')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">@icon('📁') {{ __('Documents') }}</h4>
        @if($canManage)
            <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#uploadPanel">
                @icon('⬆') {{ __('Upload') }}
            </button>
        @endif
    </div>

    {{-- Upload panel (instructors/bureau) --}}
    @if($canManage)
        <div class="collapse mb-3" id="uploadPanel">
            <div class="card dc-card">
                <div class="card-body">
                    <form method="POST" action="{{ route('documents.upload') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="folder" value="{{ $folder }}">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('Files') }}</label>
                                <input type="file" name="files[]" class="form-control form-control-sm" multiple required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">{{ __('Description') }}</label>
                                <input type="text" name="description" class="form-control form-control-sm" placeholder="{{ __('Optional') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">{{ __('Visible to') }}</label>
                                <select name="visibility" class="form-select form-select-sm">
                                    <option value="public">@icon('🌍') {{ __('Everyone (public)') }}</option>
                                    <option value="members" selected>@icon('👥') {{ __('Members') }}</option>
                                    <option value="instructors">@icon('🎓') {{ __('Instructors & Bureau') }}</option>
                                    <option value="bureau">@icon('🔒') {{ __('Bureau only') }}</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary btn-sm w-100">{{ __('Upload') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        {{-- Folder sidebar --}}
        <div class="col-md-3">
            <div class="card dc-card mb-3">
                <div class="card-header py-2">{{ __('Folders') }}</div>
                <div class="list-group list-group-flush">
                    @foreach($folders as $f)
                        @php $depth = $f === '/' ? 0 : substr_count(trim($f, '/'), '/') + 1; @endphp
                        <a href="{{ route('documents.index', ['folder' => $f]) }}"
                           class="list-group-item list-group-item-action py-1 {{ $folder === $f ? 'active' : '' }}"
                           style="padding-left:{{ 12 + $depth * 16 }}px; font-size:0.85rem">
                            @icon('📁') {{ $f === '/' ? __('Root') : basename($f) }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- New folder (instructors/bureau) --}}
            @if($canManage)
                <form method="POST" action="{{ route('documents.create-folder') }}" class="card dc-card p-2">
                    @csrf
                    <input type="hidden" name="parent" value="{{ $folder }}">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">@icon('📁')+</span>
                        <input type="text" name="name" class="form-control" placeholder="{{ __('New folder') }}" required pattern="[a-zA-Z0-9_\- ]+">
                        <button class="btn btn-outline-primary">{{ __('Create') }}</button>
                    </div>
                    <small class="text-muted mt-1">{{ __('In:') }} {{ $folder }}</small>
                </form>
            @endif
        </div>

        {{-- File list --}}
        <div class="col-md-9">
            @if($canManage)
            <div id="dropZone" class="card dc-card" style="transition:background .2s">
                {{-- Drag-drop overlay --}}
                <div id="dropOverlay" class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="display:none!important;background:rgba(0,102,204,.15);border:3px dashed #0066cc;border-radius:.375rem;z-index:10;pointer-events:none">
                    <span class="fs-4 text-primary fw-bold">@icon('📂') {{ __('Drop files here') }}</span>
                </div>
            @else
            <div class="card dc-card">
            @endif
                <div class="card-header py-2 d-flex justify-content-between">
                    <span>
                        {{-- Breadcrumb navigation --}}
                        <a href="{{ route('documents.index', ['folder' => '/']) }}" class="text-decoration-none">@icon('📂') {{ __('Root') }}</a>
                        @if($folder !== '/')
                            @php
                                $parts = array_filter(explode('/', $folder));
                                $path = '';
                            @endphp
                            @foreach($parts as $part)
                                @php $path .= '/' . $part; @endphp
                                / <a href="{{ route('documents.index', ['folder' => $path]) }}" class="text-decoration-none">{{ $part }}</a>
                            @endforeach
                        @endif
                    </span>
                    <span class="badge bg-secondary">{{ $subfolders->count() }} {{ __('folders') }}, {{ $files->count() }} {{ __('files') }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:40px"></th>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Size') }}</th>
                                <th>{{ __('Access') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        {{-- Parent folder link --}}
                        @if($folder !== '/')
                            @php $parent = dirname($folder) === '.' ? '/' : dirname($folder); @endphp
                            <tr style="cursor:pointer" onclick="window.location='{{ route('documents.index', ['folder' => $parent]) }}'">
                                <td>📁</td>
                                <td colspan="5"><a href="{{ route('documents.index', ['folder' => $parent]) }}" class="text-decoration-none">..</a></td>
                            </tr>
                        @endif

                        {{-- Subfolders --}}
                        @foreach($subfolders as $sf)
                            <tr style="cursor:pointer" onclick="window.location='{{ route('documents.index', ['folder' => $sf]) }}'">
                                <td>📁</td>
                                <td><a href="{{ route('documents.index', ['folder' => $sf]) }}" class="text-decoration-none fw-bold">{{ basename($sf) }}</a></td>
                                <td></td><td></td><td></td><td></td>
                            </tr>
                        @endforeach

                        {{-- Files --}}
                        @foreach($files as $f)
                                <tr>
                                    <td>
                                        @if($f->hasThumb())
                                            <img src="{{ route('documents.thumb', $f) }}" alt="" style="max-width:36px;max-height:36px;border-radius:3px" loading="lazy">
                                        @else
                                            @php $ext = pathinfo($f->original_name, PATHINFO_EXTENSION); @endphp
                                            @if(in_array($ext, ['pdf'])) @icon('📄')                                             @elseif(in_array($ext, ['doc','docx'])) @icon('📝')                                             @elseif(in_array($ext, ['xls','xlsx'])) @icon('📊')                                             @elseif(in_array($ext, ['ppt','pptx'])) @icon('📊')                                             @elseif(in_array($ext, ['zip','rar','7z'])) @icon('📦')                                             @else @icon('📎')                                             @endif
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('documents.download', $f) }}">{{ $f->original_name }}</a>
                                        @if($f->description)<br><small class="text-muted">{{ $f->description }}</small>@endif
                                    </td>
                                    <td class="text-nowrap small">{{ $f->humanSize() }}</td>
                                    <td>
                                        @if($canManage)
                                            {{-- Inline visibility toggle --}}
                                            <form method="POST" action="{{ route('documents.update', $f) }}" class="d-inline">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="folder" value="{{ $f->folder }}">
                                                <select name="visibility" class="form-select form-select-sm py-0" style="font-size:0.75rem;width:auto;display:inline" onchange="this.form.submit()">
                                                    @foreach(['public' => '🌍', 'members' => '👥', 'instructors' => '🎓', 'bureau' => '🔒'] as $v => $icon)
                                                        <option value="{{ $v }}" {{ $f->visibility === $v ? 'selected' : '' }}>{{ \App\Helpers\IconHelper::render($icon) }}{{ ucfirst($v) }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @else
                                            @php $icons = ['public' => '🌍', 'members' => '👥', 'instructors' => '🎓', 'bureau' => '🔒']; @endphp
                                            <span class="small">{{ \App\Helpers\IconHelper::render($icons[$f->visibility] ?? '') }}{{ ucfirst($f->visibility) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap small">
                                        {{ $f->created_at->format('d/m/Y') }}
                                        @if($f->uploader)<br><span class="text-muted">{{ $f->uploader->detail?->first_name }}</span>@endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('documents.download', $f) }}" class="btn btn-sm btn-outline-primary py-0">⬇</a>
                                        @if($canManage)
                                            <form method="POST" action="{{ route('documents.destroy', $f) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this file?') }}')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger py-0">✕</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach

                        @if($subfolders->isEmpty() && $files->isEmpty())
                            <tr><td colspan="6" class="text-muted text-center py-3">{{ __('Empty folder.') }}</td></tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Visibility legend --}}
            <div class="small text-muted mt-2">
                @icon('🌍') {{ __('Public — visible to everyone') }} ·
                @icon('👥') {{ __('Members — logged-in members') }} ·
                @icon('🎓') {{ __('Instructors — instructors & bureau') }} ·
                @icon('🔒') {{ __('Bureau — bureau only') }}
            </div>
        </div>
    </div>

    @if($canManage)
    <script>
    (function(){
        const zone = document.getElementById('dropZone');
        const overlay = document.getElementById('dropOverlay');
        let dragCounter = 0;

        zone.addEventListener('dragenter', e => { e.preventDefault(); dragCounter++; overlay.style.cssText='display:flex!important;position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,102,204,.15);border:3px dashed #0066cc;border-radius:.375rem;z-index:10;pointer-events:none;align-items:center;justify-content:center'; });
        zone.addEventListener('dragleave', e => { e.preventDefault(); if(--dragCounter<=0){dragCounter=0; overlay.style.display='none';} });
        zone.addEventListener('dragover', e => e.preventDefault());
        zone.addEventListener('drop', e => {
            e.preventDefault(); dragCounter=0; overlay.style.display='none';
            const files = e.dataTransfer.files;
            if(!files.length) return;
            const fd = new FormData();
            fd.append('_token', '{{ csrf_token() }}');
            fd.append('folder', '{{ $folder }}');
            fd.append('visibility', 'members');
            for(let i=0;i<files.length;i++) fd.append('files[]', files[i]);
            const btn = document.createElement('div');
            btn.className='alert alert-info py-2 mt-2';
            btn.textContent='@icon('⏳') {{ __("Uploading") }} '+files.length+' {{ __("file(s)…") }}';
            zone.after(btn);
            fetch('{{ route("documents.upload") }}', {method:'POST', body:fd})
                .then(r => { if(r.ok||r.redirected) location.reload(); else btn.textContent='@icon('❌') {{ __("Upload failed") }}'; })
                .catch(() => btn.textContent='@icon('❌') {{ __("Upload failed") }}');
        });
    })();
    </script>
    @endif
</x-layout>
