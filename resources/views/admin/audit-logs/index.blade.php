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
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
            <a href="{{ route('admin.audit-logs.export', request()->query()) }}" class="btn btn-outline-secondary" title="{{ __('Export CSV') }}">📥</a>
        </div>
    </form>

    {{-- Retention policy + purge --}}
    <div class="alert alert-secondary py-2 mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small>{{ __('Total entries') }}: {{ number_format($logs->total()) }} @if($oldestLog) · {{ __('Oldest') }}: {{ \Carbon\Carbon::parse($oldestLog)->format('d/m/Y') }} @endif</small>
            <div class="d-flex gap-3 align-items-center">
                {{-- Retention policy --}}
                <form method="POST" action="{{ route('admin.audit-logs.retention') }}" class="d-flex gap-1 align-items-center">
                    @csrf
                    <small class="text-nowrap">@icon('📋') {{ __('Auto-purge after') }}</small>
                    <select name="audit_retention_months" class="form-select form-select-sm" style="width:90px">
                        @foreach([6, 12, 18, 24, 36, 48, 60] as $m)
                            <option value="{{ $m }}" {{ $retentionMonths == $m ? 'selected' : '' }}>{{ $m }} {{ __('mo') }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-primary">{{ __('Set') }}</button>
                </form>
                {{-- Manual purge --}}
                <form method="POST" action="{{ route('admin.audit-logs.purge') }}" class="d-flex gap-1 align-items-center" onsubmit="return confirm('{{ __('This will permanently delete old audit log entries. Continue?') }}')">
                    @csrf
                    <small class="text-nowrap">{{ __('Delete older than') }}</small>
                    <select name="years" class="form-select form-select-sm" style="width:80px">
                        @for($i = 1; $i <= 5; $i++)<option value="{{ $i }}">{{ $i }} {{ __('yr') }}</option>@endfor
                    </select>
                    <button class="btn btn-sm btn-outline-danger text-nowrap">@icon('🗑️') {{ __('Purge') }}</button>
                </form>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead><tr><th>{{ __('Time') }}</th><th>{{ __('User') }}</th><th>{{ __('Action') }}</th><th>{{ __('Model') }}</th><th>{{ __('ID') }}</th><th>{{ __('Summary') }}</th><th></th></tr></thead>
            <tbody>
                @foreach($logs as $log)
                    <tr>
                        <td class="small">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $log->user?->name ?? $log->user_id }}</td>
                        <td><span class="badge bg-{{ $log->action === 'deleted' ? 'danger' : ($log->action === 'created' ? 'success' : 'info') }}">{{ $log->action }}</span></td>
                        <td class="small">{{ class_basename($log->model_type) }}</td>
                        <td>{{ $log->model_id }}</td>
                        <td class="small text-muted">
                            @if($log->action === 'updated' && $log->new_values)
                                {{ implode(', ', array_diff(array_keys($log->new_values), ['updated_at'])) }}
                            @endif
                        </td>
                        <td><a href="{{ route('admin.audit-logs.show', $log) }}" class="btn btn-sm btn-outline-secondary py-0">{{ __('View') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</x-layout>
