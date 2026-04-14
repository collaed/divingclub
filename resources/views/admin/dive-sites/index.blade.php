<x-layout :title="__('Dive Sites')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>@icon('🤿') {{ __('Dive Sites') }}</h4>
        <a href="{{ route('admin.dive-sites.create') }}" class="btn btn-primary btn-sm">+ {{ __('Add Site') }}</a>
    </div>

    <div class="card dc-card">
        <div class="card-header py-2">
            <input type="text" id="siteFilter" class="form-control form-control-sm" placeholder="🔍 {{ __('Filter sites…') }}" autofocus>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" id="sitesTable">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Location') }}</th><th>{{ __('Type') }}</th><th>{{ __('Max Depth') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @forelse($sites as $site)
                    <tr>
                        <td>
                            @if($site->image_path)<img src="{{ asset('storage/' . $site->image_path) }}" class="rounded me-1" style="width:30px;height:30px;object-fit:cover">@endif
                            {{ $site->name }}
                        </td>
                        <td>{{ $site->region }}{{ $site->region && $site->country ? ', ' : '' }}{{ $site->country }}</td>
                        <td><span class="badge bg-info">{{ $site->water_type ?? '—' }}</span></td>
                        <td>{{ $site->max_depth ? $site->max_depth . 'm' : '—' }}</td>
                        <td><span class="badge bg-{{ $site->is_active ? 'success' : 'secondary' }}">{{ $site->is_active ? __('Active') : __('Inactive') }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('admin.dive-sites.edit', $site) }}" class="btn btn-sm btn-outline-primary">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.dive-sites.destroy', $site) }}" class="d-inline" data-confirm="{{ __('Delete this site?') }}" data-confirm-style="danger" data-confirm-btn="{{ __('Delete') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted text-center py-3">{{ __('No dive sites yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <script>
    document.getElementById('siteFilter').addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#sitesTable tbody tr').forEach(r => r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none');
    });
    </script>
</x-layout>
