<x-layout :title="__('Documents')">
    <h4 class="mb-4">📁 {{ __('Documents') }}</h4>

    <div class="row">
        @if($folders->count() > 1)
            <div class="col-md-3">
                <div class="card dc-card mb-3">
                    <div class="card-header">{{ __('Folders') }}</div>
                    <div class="list-group list-group-flush">
                        @foreach($folders as $f)
                            <a href="{{ route('documents.index', ['folder' => $f]) }}" class="list-group-item list-group-item-action {{ $folder === $f ? 'active' : '' }}">
                                📁 {{ $f === '/' ? __('All') : basename($f) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="{{ $folders->count() > 1 ? 'col-md-9' : 'col-12' }}">
            @if($files->isEmpty())
                <div class="card dc-card"><div class="card-body text-muted text-center py-4">{{ __('No documents available.') }}</div></div>
            @else
                <div class="card dc-card">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Size') }}</th><th>{{ __('Date') }}</th><th></th></tr></thead>
                            <tbody>
                            @foreach($files as $f)
                                <tr>
                                    <td>
                                        @php $ext = pathinfo($f->original_name, PATHINFO_EXTENSION); @endphp
                                        @if(in_array($ext, ['pdf'])) 📄
                                        @elseif(in_array($ext, ['jpg','jpeg','png','gif','webp'])) 🖼️
                                        @elseif(in_array($ext, ['doc','docx'])) 📝
                                        @elseif(in_array($ext, ['xls','xlsx'])) 📊
                                        @else 📎
                                        @endif
                                        {{ $f->original_name }}
                                        @if($f->description) <br><small class="text-muted">{{ $f->description }}</small> @endif
                                    </td>
                                    <td class="text-nowrap">{{ $f->humanSize() }}</td>
                                    <td class="text-nowrap">{{ $f->created_at->format('d/m/Y') }}</td>
                                    <td class="text-end"><a href="{{ route('documents.download', $f) }}" class="btn btn-sm btn-outline-primary">⬇ {{ __('Download') }}</a></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layout>
