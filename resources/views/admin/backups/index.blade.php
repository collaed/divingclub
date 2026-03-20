<x-layout :title="__('Backups')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">💾 {{ __('Backups') }}</h4>
        <form method="POST" action="{{ route('admin.backups.create') }}" class="d-flex gap-2 align-items-center">
            @csrf
            <div class="form-check">
                <input type="checkbox" name="include_files" value="1" checked class="form-check-input" id="incFiles">
                <label class="form-check-label small" for="incFiles">{{ __('Include files') }}</label>
            </div>
            <button type="submit" class="btn btn-primary" onclick="this.disabled=true;this.innerHTML='⏳ {{ __('Creating…') }}';this.form.submit();">
                {{ __('Create Backup Now') }}
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card dc-card">
        <div class="card-body p-0">
            @if(count($backups) === 0)
                <p class="text-muted p-4 mb-0">{{ __('No backups yet. Create one above.') }}</p>
            @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Backup') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Size') }}</th>
                            <th>{{ __('DB') }}</th>
                            <th>{{ __('Files') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($backups as $b)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.backups.show', $b['filename']) }}">
                                        📦 {{ $b['filename'] }}
                                    </a>
                                </td>
                                <td>{{ $b['created_at']->format('d/m/Y H:i') }}</td>
                                <td>{{ $b['size_human'] }}</td>
                                <td>
                                    @if($b['manifest'])
                                        <span class="badge bg-primary">{{ $b['manifest']['driver'] }}</span>
                                        <small>{{ number_format($b['manifest']['total_rows']) }} {{ __('rows') }}</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($b['manifest'] && $b['manifest']['includes_files'])
                                        {{ $b['manifest']['storage_files'] }} {{ __('files') }}
                                        <small class="text-muted">({{ $b['manifest']['storage_size_human'] }})</small>
                                    @else
                                        <span class="text-muted">{{ __('DB only') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.backups.download', $b['filename']) }}" class="btn btn-sm btn-outline-primary">⬇ {{ __('Download') }}</a>
                                    <form method="POST" action="{{ route('admin.backups.destroy', $b['filename']) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this backup?') }}')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">🗑</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="card dc-card mt-3">
        <div class="card-body small text-muted">
            <strong>{{ __('Automatic backups') }}:</strong> {{ __('Every Sunday at 03:00 — DB + files, last :n retained.', ['n' => config('backup.retention', 4)]) }}
            <br>{{ __('Storage location') }}: <code>storage/app/backups/</code>
        </div>
    </div>
</x-layout>
