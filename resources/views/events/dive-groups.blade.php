<x-layout :title="__('Dive Groups') . ' — ' . $event->title">
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('events.index') }}">{{ __('Calendar') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('events.show', $event) }}">{{ $event->title }}</a></li>
        <li class="breadcrumb-item active">{{ __('Dive Groups') }}</li>
    </ol></nav>

    @php
        $canManage = auth()->user()->isBureau() || $event->instructor_id === auth()->id() || in_array(auth()->id(), $event->assistant_ids ?? []);
    @endphp

    <div class="row">
        <div class="col-lg-8">
            {{-- Existing groups --}}
            @foreach($event->diveGroups as $group)
                <div class="card dc-card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <strong>{{ $group->name }}</strong>
                            <span class="badge bg-{{ match($group->dive_mode) { 'supervised' => 'primary', 'autonomous' => 'success', 'training' => 'warning text-dark', 'certification' => 'danger', default => 'secondary' } }}">{{ ucfirst($group->dive_mode) }}</span>
                            @if($group->planned_depth)<span class="badge bg-info">{{ $group->planned_depth }}m</span>@endif
                        </div>
                        @if($canManage)
                            <form method="POST" action="{{ route('dive-groups.destroy', $group) }}" onsubmit="return confirm('{{ __('Delete this group?') }}')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">✕</button>
                            </form>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($group->notes)<p class="small text-muted mb-2">{{ $group->notes }}</p>@endif

                        <table class="table table-sm mb-2">
                            <thead><tr><th>{{ __('Member') }}</th><th>{{ __('Role') }}</th><th>{{ __('Certification') }}</th><th></th></tr></thead>
                            <tbody>
                            @foreach($group->members->sortByDesc('role') as $m)
                                @php
                                    $cert = $m->user->certificationLevels->where('category', '!=', 'specialty')->sortByDesc('rank')->first();
                                @endphp
                                <tr>
                                    <td>{{ $m->user->name }}</td>
                                    <td><span class="badge bg-{{ $m->role === 'leader' ? 'warning text-dark' : 'secondary' }}">{{ $m->role === 'leader' ? '👑 ' . __('Leader') : __('Diver') }}</span></td>
                                    <td>
                                        @if($cert)
                                            {{ $cert->code }} ({{ $cert->federation?->acronym }}) <span class="text-muted small">rank {{ $cert->rank }}</span>
                                        @else
                                            <span class="text-danger">{{ __('No certification') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($canManage)
                                            <form method="POST" action="{{ route('dive-groups.remove-member', $m) }}" class="d-inline">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger py-0 px-1">✕</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>

                        {{-- Add member to this group --}}
                        @if($canManage && $unassigned->count())
                            <form method="POST" action="{{ route('dive-groups.add-member', $group) }}" class="d-flex gap-2 align-items-end">
                                @csrf
                                <select name="user_id" class="form-select form-select-sm" style="max-width:250px" required>
                                    <option value="">{{ __('Add member…') }}</option>
                                    @foreach($unassigned as $reg)
                                        @php $uc = $reg->user->certificationLevels->where('category', '!=', 'specialty')->sortByDesc('rank')->first(); @endphp
                                        <option value="{{ $reg->user_id }}">{{ $reg->user->name }}{{ $uc ? ' — ' . $uc->code : '' }}</option>
                                    @endforeach
                                </select>
                                <select name="role" class="form-select form-select-sm" style="max-width:120px">
                                    <option value="diver">{{ __('Diver') }}</option>
                                    <option value="leader">{{ __('Leader') }}</option>
                                </select>
                                <button class="btn btn-sm btn-primary">+</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach

            @if($event->diveGroups->isEmpty())
                <div class="alert alert-info">{{ __('No dive groups created yet. Create one below.') }}</div>
            @endif

            {{-- Create new group --}}
            @if($canManage)
                <div class="card dc-card mb-3">
                    <div class="card-header">+ {{ __('New Dive Group') }}</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('events.dive-groups.store', $event) }}">
                            @csrf
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <input type="text" name="name" class="form-control form-control-sm" placeholder="{{ __('Group name') }}">
                                </div>
                                <div class="col-md-3">
                                    <select name="dive_mode" class="form-select form-select-sm" required>
                                        <option value="supervised">{{ __('Supervised') }}</option>
                                        <option value="autonomous">{{ __('Autonomous') }}</option>
                                        <option value="training">{{ __('Training') }}</option>
                                        <option value="certification">{{ __('Certification') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="planned_depth" class="form-control form-control-sm" placeholder="{{ __('Depth (m)') }}" min="1" max="300">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="{{ __('Notes') }}">
                                </div>
                                <div class="col-md-1">
                                    <button class="btn btn-primary btn-sm w-100">{{ __('Create') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            {{-- Validate button --}}
            @if($canManage && $event->diveGroups->count())
                <div class="card dc-card mb-3">
                    <div class="card-header">✅ {{ __('Validate Groups') }}</div>
                    <div class="card-body">
                        <button class="btn btn-success btn-sm w-100" id="validateBtn" onclick="validateGroups()">{{ __('Check Rules') }}</button>
                        <div id="validationResult" class="mt-2"></div>
                    </div>
                </div>
            @endif

            {{-- Unassigned participants --}}
            <div class="card dc-card mb-3">
                <div class="card-header">{{ __('Unassigned Participants') }} <span class="badge bg-secondary">{{ $unassigned->count() }}</span></div>
                <div class="list-group list-group-flush">
                    @forelse($unassigned as $reg)
                        @php $uc = $reg->user->certificationLevels->where('category', '!=', 'specialty')->sortByDesc('rank')->first(); @endphp
                        <div class="list-group-item small d-flex justify-content-between">
                            <span>{{ $reg->user->name }}</span>
                            <span>
                                @if($uc)
                                    <span class="badge bg-primary">{{ $uc->code }}</span>
                                    <span class="text-muted">{{ $uc->federation?->acronym }}</span>
                                @else
                                    <span class="badge bg-danger">{{ __('No cert') }}</span>
                                @endif
                            </span>
                        </div>
                    @empty
                        <div class="list-group-item small text-muted">{{ __('All participants assigned.') }}</div>
                    @endforelse
                </div>
            </div>

            {{-- Active rules reference --}}
            <div class="card dc-card">
                <div class="card-header">📋 {{ __('Active Rules') }}</div>
                <div class="list-group list-group-flush" style="font-size:0.8rem">
                    @foreach($rules->take(10) as $rule)
                        <div class="list-group-item py-1">
                            <strong>{{ $rule->name }}</strong>
                            <span class="badge bg-secondary">{{ $rule->scope }}</span>
                            <br><span class="text-muted">{{ $rule->description }}</span>
                        </div>
                    @endforeach
                    @if($rules->count() > 10)
                        <div class="list-group-item py-1 text-muted">… {{ __('and :count more', ['count' => $rules->count() - 10]) }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
    function validateGroups() {
        const btn = document.getElementById('validateBtn');
        const result = document.getElementById('validationResult');
        btn.disabled = true;
        btn.textContent = '{{ __("Checking…") }}';
        fetch('{{ route("events.dive-groups.validate", $event) }}')
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = '{{ __("Check Rules") }}';
                if (data.valid) {
                    result.innerHTML = '<div class="alert alert-success py-2 small mb-0">✅ {{ __("All groups comply with rules.") }}</div>';
                } else {
                    let html = '<div class="alert alert-danger py-2 small mb-0">';
                    for (const [group, issues] of Object.entries(data.violations)) {
                        html += '<strong>' + group + ':</strong><ul class="mb-1">';
                        issues.forEach(i => html += '<li>' + i + '</li>');
                        html += '</ul>';
                    }
                    html += '</div>';
                    result.innerHTML = html;
                }
            });
    }
    </script>
</x-layout>
