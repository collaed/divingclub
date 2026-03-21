<x-layout :title="__('Partnerships')">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>External Registrations</h2>
        <a href="{{ route('admin.partnerships.index') }}" class="btn btn-outline-secondary btn-sm">← Partnerships</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <table class="table table-sm">
        <thead><tr><th>Member</th><th>Club</th><th>Event</th><th>Cert</th><th>Medical</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($regs as $r)
        <tr>
            <td>{{ $r->external_member_name }}<br><small class="text-muted">{{ $r->external_member_email }}</small>
                @if($r->external_member_phone)<br><small>@icon('📞') {{ $r->external_member_phone }}</small>@endif
            </td>
            <td>{{ $r->partnership->name ?? '?' }}</td>
            <td>{{ $r->event->title ?? '?' }}<br><small>{{ $r->event->event_date ?? '' }}</small></td>
            <td>{{ $r->external_cert_level }}
                @if($r->external_member_federation)<br><small class="text-muted">{{ $r->external_member_federation }}</small>@endif
                @if($r->external_member_licence_no)<br><small class="text-muted">#{{ $r->external_member_licence_no }}</small>@endif
            </td>
            <td>@if($r->external_medical_valid_until)
                <span class="{{ $r->external_medical_valid_until->isPast() ? 'text-danger' : 'text-success' }}">{{ $r->external_medical_valid_until->format('d/m/Y') }}</span>
                @else — @endif</td>
            <td><span class="badge bg-{{ $r->status === 'approved' ? 'success' : ($r->status === 'rejected' ? 'danger' : ($r->status === 'cancelled' ? 'secondary' : 'warning')) }}">{{ $r->status }}</span></td>
            <td>
                @if($r->status === 'pending')
                <form method="POST" action="{{ route('admin.partnerships.registrations.approve', $r) }}" class="d-inline">@csrf<button class="btn btn-success btn-sm">✓</button></form>
                <form method="POST" action="{{ route('admin.partnerships.registrations.reject', $r) }}" class="d-inline">@csrf<button class="btn btn-danger btn-sm">✗</button></form>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-center text-muted">No external registrations yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $regs->links() }}
</div>
</x-layout>
