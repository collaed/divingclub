<x-admin-layout :title="__('Audit Log Detail')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">{{ __('Audit Log') }} #{{ $log->id }}</h4>
        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-sm btn-outline-secondary">← {{ __('Back') }}</a>
    </div>

    <div class="card dc-card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3"><strong>{{ __('Time') }}</strong><br>{{ $log->created_at->format('d/m/Y H:i:s') }}</div>
                <div class="col-md-3"><strong>{{ __('User') }}</strong><br>{{ $log->user?->name ?? __('System') }} (ID: {{ $log->user_id }})</div>
                <div class="col-md-2"><strong>{{ __('Action') }}</strong><br><span class="badge bg-{{ $log->action === 'deleted' ? 'danger' : ($log->action === 'created' ? 'success' : 'info') }}">{{ $log->action }}</span></div>
                <div class="col-md-2"><strong>{{ __('Model') }}</strong><br>{{ class_basename($log->model_type) }}</div>
                <div class="col-md-2"><strong>{{ __('Model ID') }}</strong><br>{{ $log->model_id }}</div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-6"><strong>{{ __('IP Address') }}</strong><br><code>{{ $log->ip_address ?? '—' }}</code></div>
                <div class="col-md-6"><strong>{{ __('User Agent') }}</strong><br><small class="text-muted">{{ \Illuminate\Support\Str::limit($log->user_agent, 120) ?? '—' }}</small></div>
            </div>
            @if($log->impersonated_user_id)
                <div class="alert alert-warning mt-3 py-2 small">@icon('⚠️') {{ __('Action performed while impersonating user') }} #{{ $log->impersonated_user_id }}</div>
            @endif
        </div>
    </div>

    {{-- Diff view --}}
    @if($log->action === 'updated' && $log->old_values && $log->new_values)
        <h5>{{ __('Changes') }}</h5>
        <table class="table table-sm table-bordered">
            <thead><tr><th>{{ __('Field') }}</th><th class="bg-danger-subtle">{{ __('Before') }}</th><th class="bg-success-subtle">{{ __('After') }}</th></tr></thead>
            <tbody>
                @foreach($log->new_values as $field => $newVal)
                    @if($field === 'updated_at') @continue @endif
                    <tr>
                        <td><code>{{ $field }}</code></td>
                        <td class="bg-danger-subtle">{{ is_array($log->old_values[$field] ?? null) ? json_encode($log->old_values[$field]) : ($log->old_values[$field] ?? '—') }}</td>
                        <td class="bg-success-subtle">{{ is_array($newVal) ? json_encode($newVal) : $newVal }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($log->action === 'created' && $log->new_values)
        <h5>{{ __('Created Values') }}</h5>
        <table class="table table-sm table-bordered">
            <thead><tr><th>{{ __('Field') }}</th><th>{{ __('Value') }}</th></tr></thead>
            <tbody>
                @foreach($log->new_values as $field => $val)
                    @if(in_array($field, ['updated_at', 'created_at', 'password', 'remember_token'])) @continue @endif
                    <tr><td><code>{{ $field }}</code></td><td>{{ is_array($val) ? json_encode($val) : $val }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @elseif($log->action === 'deleted' && $log->old_values)
        <h5>{{ __('Deleted Values') }}</h5>
        <table class="table table-sm table-bordered">
            <thead><tr><th>{{ __('Field') }}</th><th>{{ __('Value') }}</th></tr></thead>
            <tbody>
                @foreach($log->old_values as $field => $val)
                    @if(in_array($field, ['updated_at', 'created_at', 'password', 'remember_token'])) @continue @endif
                    <tr><td><code>{{ $field }}</code></td><td>{{ is_array($val) ? json_encode($val) : $val }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="alert alert-secondary">{{ __('No detailed data available for this entry.') }}</div>
    @endif
</x-admin-layout>
