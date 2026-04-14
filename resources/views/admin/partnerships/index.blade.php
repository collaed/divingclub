<x-layout :title="__('Partnerships')">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>@icon('🤝') Club Partnerships</h2>
        <div>
            <a href="{{ route('admin.partnerships.registrations') }}" class="btn btn-outline-primary btn-sm">External Registrations</a>
            <a href="{{ route('admin.partnerships.create') }}" class="btn btn-primary btn-sm">+ Add Partner</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    @forelse($partners as $p)
    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1">{{ $p->name }} @if(!$p->is_active)<span class="badge bg-secondary">Inactive</span>@endif</h5>
                <small class="text-muted">{{ $p->base_url }}</small><br>
                <small>Key ID: <code>{{ $p->api_key_id }}</code> · Registrations: {{ $p->external_registrations_count }} · Last sync: {{ $p->last_sync_at?->diffForHumans() ?? 'never' }}</small>
            </div>
            <div>
                @if($p->their_api_key_id)
                <a href="{{ route('admin.partnerships.remote-events', $p) }}" class="btn btn-outline-info btn-sm">Browse Events</a>
                @endif
                <form method="POST" data-confirm="Delete?" data-confirm-style="danger" data-confirm-btn="{{ __('Confirm') }}" action="{{ route('admin.partnerships.destroy', $p) }}" class="d-inline" data-confirm="Remove partnership?" data-confirm-style="danger" data-confirm-btn="{{ __('Confirm') }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm">Remove</button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="alert alert-info">No partner clubs configured. Add a partnership to enable inter-club event sharing.</div>
    @endforelse

    <div class="card mt-4">
        <div class="card-header">How it works</div>
        <div class="card-body small">
            <ol>
                <li><strong>Add a partner</strong> — generates a Key ID + Secret. Share these with the partner club.</li>
                <li><strong>They add you</strong> — they generate their own Key ID + Secret and share them with you (enter as "Their credentials").</li>
                <li><strong>Mark events as "Federated"</strong> — set external slots when editing an event.</li>
                <li><strong>Partner members register</strong> — their club sends registrations via API. You approve/reject in "External Registrations".</li>
            </ol>
            <p class="mb-0">API endpoint: <code>{{ url('/api/federation/events') }}</code></p>
        </div>
    </div>
</div>
</x-layout>
