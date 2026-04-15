<x-admin-layout :title="__('Email Delivery Stats')">
    <h4 class="mb-3">📊 {{ __('Email Delivery Stats') }}</h4>

    {{-- Date picker --}}
    <form class="d-flex align-items-center gap-2 mb-4" method="GET">
        <a href="{{ route('admin.email-stats', ['date' => \Carbon\Carbon::parse($date)->subDay()->toDateString()]) }}" class="btn btn-sm btn-outline-secondary">◀</a>
        <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm" style="width:auto" onchange="this.form.submit()">
        <a href="{{ route('admin.email-stats', ['date' => \Carbon\Carbon::parse($date)->addDay()->toDateString()]) }}" class="btn btn-sm btn-outline-secondary">▶</a>
        <a href="{{ route('admin.email-stats') }}" class="btn btn-sm btn-outline-primary">{{ __('Today') }}</a>
    </form>

    {{-- Summary --}}
    @if($totals['messages'] > 0)
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card dc-card text-center p-3"><div class="fs-3 fw-bold">{{ $totals['messages'] }}</div><small class="text-muted">{{ __('Messages') }}</small></div></div>
        <div class="col-md-3"><div class="card dc-card text-center p-3"><div class="fs-3 fw-bold text-success">{{ $totals['opened'] }}</div><small class="text-muted">{{ __('Opened') }}</small></div></div>
        <div class="col-md-3"><div class="card dc-card text-center p-3"><div class="fs-3 fw-bold text-primary">{{ $totals['clicked'] }}</div><small class="text-muted">{{ __('Clicked') }}</small></div></div>
        <div class="col-md-3"><div class="card dc-card text-center p-3"><div class="fs-3 fw-bold text-danger">{{ $totals['failed'] }}</div><small class="text-muted">{{ __('Failed') }}</small></div></div>
    </div>
    @endif

    {{-- Per subject --}}
    @forelse($subjects as $subject => $recipients)
        <div class="card dc-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>{{ $subject }}</strong>
                @php
                    $opened = $recipients->whereIn('status', ['opened','clicked'])->count();
                    $total = $recipients->count();
                    $pct = $total ? round($opened / $total * 100) : 0;
                @endphp
                <span class="text-muted small">
                    {{ $total }} {{ __('sent') }} · {{ $opened }} {{ __('read') }} ({{ $pct }}%)
                </span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Status') }}</th><th>{{ __('Time') }}</th></tr></thead>
                    <tbody>
                        @foreach($recipients as $r)
                            <tr>
                                <td>{{ $r['first_name'] }} {{ $r['last_name'] }}</td>
                                <td class="text-muted small">{{ $r['email'] }}</td>
                                <td>
                                    @if($r['status'] === 'clicked')
                                        <span class="badge bg-success">✓ {{ __('Clicked') }}</span>
                                    @elseif($r['status'] === 'opened')
                                        <span class="badge bg-success bg-opacity-75">👁 {{ __('Opened') }}</span>
                                    @elseif($r['status'] === 'sent')
                                        <span class="badge bg-warning text-dark">📤 {{ __('Sent') }}</span>
                                    @else
                                        <span class="badge bg-danger">✗ {{ __('Failed') }}</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ \Illuminate\Support\Str::substr($r['arrived_at'], 11, 5) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="text-muted text-center py-5">
            {{ __('No emails sent on this date.') }}
            @if(!env('MAILJET_KEY'))
                <br><small>{{ __('Configure MAILJET_KEY and MAILJET_SECRET in .env to enable tracking.') }}</small>
            @endif
        </div>
    @endforelse
</x-admin-layout>
