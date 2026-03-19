{{-- Document browser with role-based visibility, upload & folder management | ClubCEP.eu --}}
<x-layout :title="__('Documents')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">📁 {{ __('Documents') }}</h4>
        @if($canManage)
            <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#uploadPanel">
                ⬆ {{ __('Upload') }}
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
                                    <option value="public">🌍 {{ __('Everyone (public)') }}</option>
                                    <option value="members" selected>👥 {{ __('Members') }}</option>
                                    <option value="instructors">🎓 {{ __('Instructors & Bureau') }}</option>
                                    <option value="bureau">🔒 {{ __('Bureau only') }}</option>
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
                        @php $depth = substr_count(trim($f, '/'), '/'); @endphp
                        <a href="{{ route('documents.index', ['folder' => $f]) }}"
                           class="list-group-item list-group-item-action py-1 {{ $folder === $f ? 'active' : '' }}"
                           style="padding-left:{{ 12 + $depth * 16 }}px; font-size:0.85rem">
                            📁 {{ $f === '/' ? __('Root') : basename($f) }}
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
                        <span class="input-group-text">📁+</span>
                        <input type="text" name="name" class="form-control" placeholder="{{ __('New folder') }}" required pattern="[a-zA-Z0-9_\- ]+">
                        <button class="btn btn-outline-primary">{{ __('Create') }}</button>
                    </div>
                    <small class="text-muted mt-1">{{ __('In:') }} {{ $folder }}</small>
                </form>
            @endif
        </div>

        {{-- File list --}}
        <div class="col-md-9">
            <div class="card dc-card">
                <div class="card-header py-2 d-flex justify-content-between">
                    <span>📂 {{ $folder }}</span>
                    <span class="badge bg-secondary">{{ $files->count() }} {{ __('files') }}</span>
                </div>
                @if($files->isEmpty())
                    <div class="card-body text-muted text-center py-4">{{ __('No files in this folder.') }}</div>
                @else
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
                            @foreach($files as $f)
                                <tr>
                                    <td>
                                        @if($f->hasThumb())
                                            <img src="{{ route('documents.thumb', $f) }}" alt="" style="max-width:36px;max-height:36px;border-radius:3px" loading="lazy">
                                        @else
                                            @php $ext = pathinfo($f->original_name, PATHINFO_EXTENSION); @endphp
                                            @if(in_array($ext, ['pdf'])) 📄
                                            @elseif(in_array($ext, ['doc','docx'])) 📝
                                            @elseif(in_array($ext, ['xls','xlsx'])) 📊
                                            @elseif(in_array($ext, ['ppt','pptx'])) 📊
                                            @elseif(in_array($ext, ['zip','rar','7z'])) 📦
                                            @else 📎
                                            @endif
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
                                                        <option value="{{ $v }}" {{ $f->visibility === $v ? 'selected' : '' }}>{{ $icon }} {{ ucfirst($v) }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @else
                                            @php $icons = ['public' => '🌍', 'members' => '👥', 'instructors' => '🎓', 'bureau' => '🔒']; @endphp
                                            <span class="small">{{ $icons[$f->visibility] ?? '' }} {{ ucfirst($f->visibility) }}</span>
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
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Visibility legend --}}
            <div class="small text-muted mt-2">
                🌍 {{ __('Public — visible to everyone') }} ·
                👥 {{ __('Members — logged-in members') }} ·
                🎓 {{ __('Instructors — instructors & bureau') }} ·
                🔒 {{ __('Bureau — bureau only') }}
            </div>
        </div>
    </div>
</x-layout>
