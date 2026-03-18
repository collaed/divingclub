<x-layout :title="__('Annual Report') . ' ' . $year">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">📊 {{ __('Annual Report') }} — {{ $year }}</h4>
        <form method="GET" class="d-flex gap-2">
            <select name="year" class="form-select form-select-sm" style="width:100px" onchange="this.form.submit()">
                @foreach($years as $y)<option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>@endforeach
            </select>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">🖨️ {{ __('Print') }}</button>
        </form>
    </div>

    {{-- KPI cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card dc-card text-center p-3"><h3>{{ $membersByStatus->sum('users_count') }}</h3><small class="text-muted">{{ __('Total Members') }}</small></div></div>
        <div class="col-md-3"><div class="card dc-card text-center p-3"><h3>{{ $newMembers }}</h3><small class="text-muted">{{ __('New Members') }}</small></div></div>
        <div class="col-md-3"><div class="card dc-card text-center p-3"><h3>{{ $totalEvents }}</h3><small class="text-muted">{{ __('Events Held') }}</small></div></div>
        <div class="col-md-3"><div class="card dc-card text-center p-3"><h3 class="text-success">€{{ number_format($finance['revenue'], 2) }}</h3><small class="text-muted">{{ __('Revenue Collected') }}</small></div></div>
    </div>

    {{-- Charts row 1: Members trend + Events by type --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card dc-card">
                <div class="card-header">{{ __('Members Over Time') }}</div>
                <div class="card-body"><canvas id="membersTrendChart" height="200"></canvas></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card dc-card">
                <div class="card-header">{{ __('Events by Type') }}</div>
                <div class="card-body"><canvas id="eventsByTypeChart" height="200"></canvas></div>
            </div>
        </div>
    </div>

    {{-- Charts row 2: Monthly participation + Social vs Diving --}}
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card dc-card">
                <div class="card-header">{{ __('Monthly Participation') }}</div>
                <div class="card-body"><canvas id="monthlyChart" height="180"></canvas></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card dc-card">
                <div class="card-header">{{ __('Activity Breakdown') }}</div>
                <div class="card-body"><canvas id="activityChart" height="200"></canvas></div>
            </div>
        </div>
    </div>

    {{-- Financial summary --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card dc-card">
                <div class="card-header">{{ __('Financial Summary') }}</div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr><td>{{ __('Total Dues Expected') }}</td><td class="text-end fw-bold">€{{ number_format($finance['total_due'], 2) }}</td></tr>
                        <tr class="text-success"><td>{{ __('Collected') }} ({{ $finance['paid_count'] }} {{ __('members') }})</td><td class="text-end fw-bold">€{{ number_format($finance['revenue'], 2) }}</td></tr>
                        <tr class="text-warning"><td>{{ __('Outstanding') }} ({{ $finance['pending_count'] }} {{ __('members') }})</td><td class="text-end fw-bold">€{{ number_format($finance['outstanding'], 2) }}</td></tr>
                        @if($finance['total_due'] > 0)
                        <tr><td>{{ __('Collection Rate') }}</td><td class="text-end fw-bold">{{ round($finance['revenue'] / $finance['total_due'] * 100) }}%</td></tr>
                        @endif
                    </table>
                    <canvas id="financeChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card dc-card">
                <div class="card-header">{{ __('Members by Status') }}</div>
                <div class="card-body"><canvas id="statusChart" height="200"></canvas></div>
            </div>
        </div>
    </div>

    <script>
    const colors = ['#003366','#0077be','#28a745','#ffc107','#dc3545','#6f42c1','#17a2b8','#fd7e14'];

    new Chart(document.getElementById('membersTrendChart'), {
        type: 'line',
        data: {
            labels: {!! json_encode($membersTrend->pluck('year')) !!},
            datasets: [{label: '{{ __("Members") }}', data: {!! json_encode($membersTrend->pluck('count')) !!}, borderColor: '#0077be', backgroundColor: 'rgba(0,119,190,0.1)', fill: true, tension: 0.3}]
        },
        options: {scales: {y: {beginAtZero: true}}}
    });

    new Chart(document.getElementById('eventsByTypeChart'), {
        type: 'doughnut',
        data: {
            labels: {!! json_encode($eventsByType->keys()) !!},
            datasets: [{data: {!! json_encode($eventsByType->values()) !!}, backgroundColor: colors}]
        }
    });

    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($monthlyParticipation->pluck('label')) !!},
            datasets: [{label: '{{ __("Registrations") }}', data: {!! json_encode($monthlyParticipation->pluck('count')) !!}, backgroundColor: '#0077be'}]
        },
        options: {scales: {y: {beginAtZero: true}}}
    });

    new Chart(document.getElementById('activityChart'), {
        type: 'doughnut',
        data: {
            labels: ['{{ __("Diving/Pool/Training") }}', '{{ __("Social") }}', '{{ __("Theory") }}'],
            datasets: [{data: [{{ $socialVsDiving['diving'] }}, {{ $socialVsDiving['social'] }}, {{ $socialVsDiving['theory'] }}], backgroundColor: ['#003366','#ffc107','#6f42c1']}]
        }
    });

    new Chart(document.getElementById('financeChart'), {
        type: 'bar',
        data: {
            labels: ['{{ __("Collected") }}', '{{ __("Outstanding") }}'],
            datasets: [{data: [{{ $finance['revenue'] }}, {{ $finance['outstanding'] }}], backgroundColor: ['#28a745','#ffc107']}]
        },
        options: {indexAxis: 'y', scales: {x: {beginAtZero: true}}, plugins: {legend: {display: false}}}
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'pie',
        data: {
            labels: {!! json_encode($membersByStatus->pluck('name')) !!},
            datasets: [{data: {!! json_encode($membersByStatus->pluck('users_count')) !!}, backgroundColor: colors}]
        }
    });
    </script>
</x-layout>
