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
