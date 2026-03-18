<x-layout :title="__('Document Library')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">📁 {{ __('Document Library') }}</h4>
    </div>

    <div class="row">
        {{-- Folder sidebar --}}
        <div class="col-md-3">
            <div class="card dc-card mb-3">
                <div class="card-header">{{ __('Folders') }}</div>
                <div class="list-group list-group-flush">
                    @foreach($folders as $f)
                        <a href="{{ route('admin.library.index', ['folder' => $f]) }}" class="list-group-item list-group-item-action {{ $folder === $f ? 'active' : '' }}">
                            📁 {{ $f === '/' ? __('Root') : basename($f) }}
                        </a>
                    @endforeach
                </div>
            </div>
            {{-- New folder --}}
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
            {{-- Upload --}}
            <div class="card dc-card mb-3">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.library.upload') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="folder" value="{{ $folder }}">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label">{{ __('Files') }}</label>
                                <input type="file" name="files[]" class="form-control form-control-sm" multiple required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Description') }}</label>
                                <input type="text" name="description" class="form-control form-control-sm" placeholder="{{ __('Optional') }}">
                            </div>
                            <div class="col-md-2">
                                <div class="form-check">
                                    <input type="hidden" name="is_public" value="0">
                                    <input type="checkbox" name="is_public" value="1" class="form-check-input" id="uploadPublic">
                                    <label class="form-check-label" for="uploadPublic">{{ __('Public') }}</label>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary btn-sm w-100">{{ __('Upload') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Current folder --}}
            <div class="card dc-card">
                <div class="card-header">📂 {{ $folder }}</div>
                @if($files->isEmpty())
                    <div class="card-body text-muted">{{ __('No files in this folder.') }}</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th style="width:50px"></th><th>{{ __('Name') }}</th><th>{{ __('Size') }}</th><th>{{ __('Visibility') }}</th><th>{{ __('Uploaded') }}</th><th></th></tr></thead>
                            <tbody>
                            @foreach($files as $f)
                                <tr>
                                    <td>
                                        @if($f->hasThumb())
                                            <img src="{{ route('admin.library.thumb', $f) }}" alt="" style="max-width:40px;max-height:40px;border-radius:3px" loading="lazy">
                                        @else
                                            @php $ext = pathinfo($f->original_name, PATHINFO_EXTENSION); @endphp
                                            @if(in_array($ext, ['pdf'])) 📄
                                            @elseif(in_array($ext, ['doc','docx'])) 📝
                                            @elseif(in_array($ext, ['xls','xlsx'])) 📊
                                            @else 📎
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.library.download', $f) }}">{{ $f->original_name }}</a>
                                        @if($f->description) <br><small class="text-muted">{{ $f->description }}</small> @endif
                                    </td>
                                    <td class="text-nowrap">{{ $f->humanSize() }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.library.update', $f) }}" class="d-inline">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="folder" value="{{ $f->folder }}">
                                            <input type="hidden" name="is_public" value="{{ $f->is_public ? '0' : '1' }}">
                                            <button class="btn btn-sm {{ $f->is_public ? 'btn-success' : 'btn-outline-secondary' }}">
                                                {{ $f->is_public ? __('Public') : __('Private') }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-nowrap small">{{ $f->created_at->format('d/m/Y') }}<br>{{ $f->uploader?->name }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.library.destroy', $f) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete?') }}')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">✕</button>
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
</x-layout>
