<x-layout :title="__('Backup') . ' — ' . $filename">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">@icon('📦') {{ $filename }}</h4>
        <div>
            <a href="{{ route('admin.backups.download', $filename) }}" class="btn btn-primary">@icon('⬇') {{ __('Download') }}</a>
            <a href="{{ route('admin.backups.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
        </div>
    </div>

    <div class="row g-3">
        {{-- Manifest summary --}}
        <div class="col-md-6">
            <div class="card dc-card">
                <div class="card-header">{{ __('Backup Info') }}</div>
                <div class="card-body">
                    @if($manifest)
                        <table class="table table-sm table-borderless mb-0">
                            <tr><th class="text-muted" style="width:160px">{{ __('Created') }}</th><td>{{ \Carbon\Carbon::parse($manifest['created_at'])->format('d/m/Y H:i:s') }}</td></tr>
                            <tr><th class="text-muted">{{ __('Archive size') }}</th><td>{{ $size_human }}</td></tr>
                            <tr><th class="text-muted">{{ __('DB driver') }}</th><td><span class="badge bg-primary">{{ $manifest['driver'] }}</span> {{ $manifest['database'] }}</td></tr>
                            <tr><th class="text-muted">{{ __('Total rows') }}</th><td>{{ number_format($manifest['total_rows']) }}</td></tr>
                            <tr><th class="text-muted">{{ __('Tables') }}</th><td>{{ count($manifest['tables']) }}</td></tr>
                            <tr><th class="text-muted">{{ __('Files included') }}</th><td>
                                @if($manifest['includes_files'])
                                    @icon('✅') {{ $manifest['storage_files'] }} {{ __('files') }} ({{ $manifest['storage_size_human'] }})
                                @else
                                    @icon('❌') {{ __('DB only') }}
                                @endif
                            </td></tr>
                            <tr><th class="text-muted">{{ __('App version') }}</th><td>{{ $manifest['version'] ?? '—' }}</td></tr>
                            <tr><th class="text-muted">{{ __('PHP') }}</th><td>{{ $manifest['php_version'] ?? '—' }}</td></tr>
                            <tr><th class="text-muted">{{ __('Laravel') }}</th><td>{{ $manifest['laravel_version'] ?? '—' }}</td></tr>
                        </table>
                    @else
                        <p class="text-muted mb-0">{{ __('No manifest found — legacy backup format.') }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Table row counts --}}
        <div class="col-md-6">
            <div class="card dc-card">
                <div class="card-header">{{ __('Database Tables') }}</div>
                <div class="card-body" style="max-height:400px; overflow-y:auto;">
                    @if($manifest && !empty($manifest['tables']))
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Table') }}</th><th class="text-end">{{ __('Rows') }}</th></tr></thead>
                            <tbody>
                                @foreach(collect($manifest['tables'])->sortByDesc(fn($v) => $v) as $table => $count)
                                    <tr>
                                        <td><code>{{ $table }}</code></td>
                                        <td class="text-end">{{ number_format($count) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted mb-0">—</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Storage files breakdown --}}
    @if(!empty($files))
        <div class="card dc-card mt-3">
            <div class="card-header">{{ __('Storage Files') }}</div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach($files as $folder => $items)
                        <div class="col-md-4">
                            <div class="border rounded p-2">
                                <strong>@icon('📁') {{ $folder }}/</strong>
                                <span class="badge bg-secondary float-end">{{ count($items) }}</span>
                                <ul class="list-unstyled small mt-1 mb-0" style="max-height:200px; overflow-y:auto;">
                                    @foreach(array_slice($items, 0, 20) as $f)
                                        <li class="text-truncate text-muted" title="{{ $f }}">{{ basename($f) }}</li>
                                    @endforeach
                                    @if(count($items) > 20)
                                        <li class="text-muted fst-italic">… {{ __('and :n more', ['n' => count($items) - 20]) }}</li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-layout>
