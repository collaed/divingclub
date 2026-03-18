<x-layout :title="__('Audit Log')">
    <h4 class="mb-4">{{ __('Audit Log') }}</h4>

    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-2">
            <input type="number" name="user_id" class="form-control" placeholder="{{ __('User ID') }}" value="{{ request('user_id') }}">
        </div>
        <div class="col-md-2">
            <select name="action" class="form-select">
                <option value="">{{ __('All Actions') }}</option>
                @foreach(['created', 'updated', 'deleted', 'sso_linked', 'impersonate_start'] as $a)
                    <option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <input type="text" name="model_type" class="form-control" placeholder="{{ __('Model type') }}" value="{{ request('model_type') }}">
        </div>
        <div class="col-md-2">
            <input type="date" name="from" class="form-control" value="{{ request('from') }}">
        </div>
        <div class="col-md-2">
            <input type="date" name="to" class="form-control" value="{{ request('to') }}">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
        </div>
    </form>

    {{-- Purge old entries --}}
    <div class="alert alert-secondary d-flex justify-content-between align-items-center py-2 mb-3">
        <small>{{ __('Total entries') }}: {{ number_format($logs->total()) }} @if($oldestLog) · {{ __('Oldest') }}: {{ \Carbon\Carbon::parse($oldestLog)->format('d/m/Y') }} @endif</small>
        <form method="POST" action="{{ route('admin.audit-logs.purge') }}" class="d-flex gap-2 align-items-center" onsubmit="return confirm('{{ __('This will permanently delete old audit log entries. Continue?') }}')">
            @csrf
            <small class="text-nowrap">{{ __('Delete entries older than') }}</small>
            <select name="years" class="form-select form-select-sm" style="width:80px">
                @for($i = 1; $i <= 5; $i++)<option value="{{ $i }}">{{ $i }} {{ __('yr') }}</option>@endfor
            </select>
            <button class="btn btn-sm btn-outline-danger text-nowrap">🗑️ {{ __('Purge') }}</button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead><tr><th>{{ __('Time') }}</th><th>{{ __('User') }}</th><th>{{ __('Action') }}</th><th>{{ __('Model') }}</th><th>{{ __('ID') }}</th><th>{{ __('Changes') }}</th></tr></thead>
            <tbody>
                @foreach($logs as $log)
                    <tr>
                        <td class="small">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $log->user?->name ?? $log->user_id }}</td>
                        <td><span class="badge bg-{{ $log->action === 'deleted' ? 'danger' : ($log->action === 'created' ? 'success' : 'info') }}">{{ $log->action }}</span></td>
                        <td class="small">{{ class_basename($log->model_type) }}</td>
                        <td>{{ $log->model_id }}</td>
                        <td class="small">
                            @if($log->old_values)
                                <details><summary>{{ __('Old') }}</summary><pre class="mb-0">{{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}</pre></details>
                            @endif
                            @if($log->new_values)
                                <details><summary>{{ __('New') }}</summary><pre class="mb-0">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre></details>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</x-layout>
