<x-layout :title="__('Dashboard')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">{{ __('Statistics Dashboard') }}</h4>
        <div class="d-flex gap-2">
            <form method="GET" class="d-inline"><input type="number" name="season" value="{{ $season }}" class="form-control form-control-sm" style="width:100px" onchange="this.form.submit()"></form>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">{{ __('Export CSV') }}</button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('admin.dashboard.export', ['type' => 'members']) }}">{{ __('Members') }}</a></li>
                    <li><a class="dropdown-item" href="{{ route('admin.dashboard.export', ['type' => 'payments']) }}">{{ __('Payments') }}</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card dc-card text-center p-3"><h3>{{ $stats['total_members'] }}</h3><small class="text-muted">{{ __('Total Members') }}</small></div></div>
        <div class="col-md-3"><div class="card dc-card text-center p-3"><h3>{{ $stats['new_members_this_year'] }}</h3><small class="text-muted">{{ __('New This Year') }}</small></div></div>
        <div class="col-md-3"><div class="card dc-card text-center p-3"><h3>{{ $stats['events_count'] }}</h3><small class="text-muted">{{ __('Events') }}</small></div></div>
        <div class="col-md-3"><div class="card dc-card text-center p-3"><h3>{{ $stats['avg_attendance'] }}</h3><small class="text-muted">{{ __('Avg Attendance') }}</small></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card dc-card text-center p-3"><h3 class="text-success">€{{ number_format($stats['revenue'], 2) }}</h3><small class="text-muted">{{ __('Revenue') }}</small></div></div>
        <div class="col-md-3"><div class="card dc-card text-center p-3"><h3 class="text-warning">€{{ number_format($stats['outstanding'], 2) }}</h3><small class="text-muted">{{ __('Outstanding') }}</small></div></div>
        <div class="col-md-3"><div class="card dc-card text-center p-3"><h3 class="text-danger">{{ $stats['certs_expiring_30d'] }}</h3><small class="text-muted">{{ __('Certs Expiring 30d') }}</small></div></div>
        <div class="col-md-3"><div class="card dc-card text-center p-3"><h3>{{ $stats['equipment_by_status']->sum() }}</h3><small class="text-muted">{{ __('Equipment Items') }}</small></div></div>
    </div>

    @php $worklistCount = collect($worklist)->reject(fn($v) => $v instanceof \Illuminate\Support\Collection || $v instanceof \Illuminate\Database\Eloquent\Collection)->sum() + ($worklist['birthdays_14d']->count()); @endphp
    @if($worklistCount > 0)
    <div class="card dc-card mb-4 border-warning">
        <div class="card-header bg-warning bg-opacity-10">📋 {{ __('Bureau Worklist') }}</div>
        <div class="list-group list-group-flush">
            @if($worklist['unverified_certs'] > 0)
                <a href="{{ route('admin.members.index') }}?filter=unverified_cert" class="list-group-item list-group-item-action d-flex justify-content-between">{{ __('Medical certificates to verify') }} <span class="badge bg-danger">{{ $worklist['unverified_certs'] }}</span></a>
            @endif
            @if($worklist['expiring_certs'] > 0)
                <a href="{{ route('admin.members.index') }}?filter=expiring_cert" class="list-group-item list-group-item-action d-flex justify-content-between">{{ __('Certificates expiring within 30 days') }} <span class="badge bg-warning text-dark">{{ $worklist['expiring_certs'] }}</span></a>
            @endif
            @if($worklist['missing_medical'] > 0)
                <a href="{{ route('admin.members.index') }}?filter=no_medical" class="list-group-item list-group-item-action d-flex justify-content-between">{{ __('Active members without medical cert') }} <span class="badge bg-danger">{{ $worklist['missing_medical'] }}</span></a>
            @endif
            @if($worklist['pending_payments'] > 0)
                <a href="{{ route('admin.payments.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between">{{ __('Pending payments') }} <span class="badge bg-warning text-dark">{{ $worklist['pending_payments'] }}</span></a>
            @endif
            @if($worklist['pending_external_regs'] > 0)
                <a href="{{ route('admin.partnerships.registrations') }}" class="list-group-item list-group-item-action d-flex justify-content-between">{{ __('External registrations to review') }} <span class="badge bg-info">{{ $worklist['pending_external_regs'] }}</span></a>
            @endif
            @if($worklist['unverified_emails'] > 0)
                <span class="list-group-item d-flex justify-content-between">{{ __('Members with unverified email') }} <span class="badge bg-secondary">{{ $worklist['unverified_emails'] }}</span></span>
            @endif
            @if($worklist['missing_iban'] > 0)
                <span class="list-group-item d-flex justify-content-between">{{ __('Active members without IBAN') }} <span class="badge bg-secondary">{{ $worklist['missing_iban'] }}</span></span>
            @endif
            @if($worklist['new_members_unconfirmed'] > 0)
                <a href="{{ route('admin.members.index') }}?filter=no_status" class="list-group-item list-group-item-action d-flex justify-content-between">{{ __('New members to confirm (no status)') }} <span class="badge bg-info">{{ $worklist['new_members_unconfirmed'] }}</span></a>
            @endif
            @if($worklist['unmatched_transactions'] > 0)
                <a href="{{ route('admin.payments.reconciliation') }}" class="list-group-item list-group-item-action d-flex justify-content-between">{{ __('Unmatched bank transactions') }} <span class="badge bg-warning text-dark">{{ $worklist['unmatched_transactions'] }}</span></a>
            @endif
            @if($worklist['minors_no_guardian'] > 0)
                <a href="{{ route('admin.guardians.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between">👨‍👧 {{ __('Minors without guardian') }} <span class="badge bg-danger">{{ $worklist['minors_no_guardian'] }}</span></a>
            @endif
            @if($worklist['birthdays_14d']->count() > 0)
                <div class="list-group-item">
                    🎂 {{ __('Birthdays next 2 weeks') }}
                    <ul class="mb-0 mt-1 small">
                        @foreach($worklist['birthdays_14d'] as $bd)
                            <li>{{ $bd->first_name }} {{ $bd->last_name }} — {{ $bd->date_of_birth->format('d/m') }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card dc-card">
                <div class="card-header">{{ __('Members by Status') }}</div>
                <div class="card-body">
                    <canvas id="statusChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card dc-card">
                <div class="card-header">{{ __('Equipment by Status') }}</div>
                <div class="card-body">
                    <canvas id="equipChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-3">
        {{-- Upcoming birthdays --}}
        <div class="col-md-4">
            <div class="card dc-card">
                <div class="card-header">🎂 {{ __('Upcoming Birthdays') }}</div>
                <div class="list-group list-group-flush">
                    @forelse($stats['upcoming_birthdays'] as $bd)
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <span>{{ $bd->first_name }} {{ $bd->last_name }}</span>
                            <span class="badge bg-info">{{ $bd->date_of_birth->format('d/m') }}</span>
                        </div>
                    @empty
                        <div class="list-group-item text-muted small">{{ __('No birthdays in the next 30 days') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Next events --}}
        <div class="col-md-4">
            <div class="card dc-card">
                <div class="card-header">📅 {{ __('Next Events') }}</div>
                <div class="list-group list-group-flush">
                    @forelse($stats['next_events'] as $ev)
                        <a href="{{ route('events.show', $ev) }}" class="list-group-item list-group-item-action py-2">
                            <div class="d-flex justify-content-between">
                                <span>{{ $ev->title }}</span>
                                <small class="text-muted">{{ $ev->event_date->format('d/m') }}</small>
                            </div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted small">{{ __('No upcoming events') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Quick actions --}}
        <div class="col-md-4">
            <div class="card dc-card">
                <div class="card-header">⚡ {{ __('Quick Actions') }}</div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.email.index') }}" class="btn btn-sm btn-outline-primary">✉️ {{ __('Send Email') }}</a>
                        <a href="{{ route('admin.payments.reconciliation') }}" class="btn btn-sm btn-outline-primary">🏦 {{ __('Bank Reconciliation') }}</a>
                        <a href="{{ route('admin.equipment.create') }}" class="btn btn-sm btn-outline-primary">🤿 {{ __('Add Equipment') }}</a>
                        <a href="{{ route('admin.guide.index') }}" class="btn btn-sm btn-outline-secondary">📖 {{ __('Admin Guide') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($stats['members_by_status']->pluck('name')) !!},
                datasets: [{data: {!! json_encode($stats['members_by_status']->pluck('count')) !!}, backgroundColor: ['#003366','#0077be','#28a745','#ffc107','#dc3545','#6f42c1']}]
            }
        });
        new Chart(document.getElementById('equipChart'), {
            type: 'bar',
            data: {
                labels: {!! json_encode($stats['equipment_by_status']->keys()) !!},
                datasets: [{label: 'Count', data: {!! json_encode($stats['equipment_by_status']->values()) !!}, backgroundColor: '#0077be'}]
            },
            options: {scales: {y: {beginAtZero: true}}}
        });
    </script>
</x-layout>
