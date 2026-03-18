<x-layout :title="__('Dive Groups') . ' — ' . $event->title">
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('events.index') }}">{{ __('Calendar') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('events.show', $event) }}">{{ $event->title }}</a></li>
        <li class="breadcrumb-item active">{{ __('Dive Groups') }}</li>
    </ol></nav>

    @php
        $canManage = auth()->user()->isBureau() || $event->instructor_id === auth()->id() || in_array(auth()->id(), $event->assistant_ids ?? []);

        // Color coding by certification rank
        function rankColor($rank) {
            if (!$rank) return '#f8d7da'; // red-ish — no cert
            if ($rank <= 20) return '#d4edda'; // green — beginner (1★/OWD)
            if ($rank <= 45) return '#cce5ff'; // blue — intermediate (2★/AOWD)
            if ($rank <= 69) return '#d1ecf1'; // cyan — advanced (3★)
            if ($rank <= 99) return '#fff3cd'; // yellow — guide de palanquée (4★)
            return '#e2d5f1'; // purple — instructor
        }
        function rankBadge($rank) {
            if (!$rank) return 'danger';
            if ($rank <= 20) return 'success';
            if ($rank <= 45) return 'primary';
            if ($rank <= 69) return 'info';
            if ($rank <= 99) return 'warning';
            return 'purple';
        }

        $purposes = [
            'explo' => ['label' => __('Exploration'), 'icon' => '🌊', 'color' => '#0d6efd'],
            'exercise' => ['label' => __('Exercise'), 'icon' => '🏋️', 'color' => '#198754'],
            'certify' => ['label' => __('Certification'), 'icon' => '🎓', 'color' => '#dc3545'],
            'autonomous_training' => ['label' => __('Autonomous Training'), 'icon' => '🧭', 'color' => '#fd7e14'],
            'bapteme' => ['label' => __('Try Dive'), 'icon' => '🐠', 'color' => '#20c997'],
            'navigation' => ['label' => __('Navigation'), 'icon' => '🧭', 'color' => '#6f42c1'],
            'night' => ['label' => __('Night Dive'), 'icon' => '🌙', 'color' => '#343a40'],
            'deep' => ['label' => __('Deep Dive'), 'icon' => '⬇️', 'color' => '#003366'],
            'rescue' => ['label' => __('Rescue Exercise'), 'icon' => '🚑', 'color' => '#e74c3c'],
        ];
    @endphp

    <style>
        .dg-board { display: flex; gap: 16px; overflow-x: auto; padding-bottom: 16px; align-items: flex-start; }
        .dg-column { min-width: 260px; max-width: 300px; flex-shrink: 0; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; }
        .dg-column-header { padding: 10px 12px; font-weight: 600; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        .dg-column-body { padding: 8px; min-height: 80px; }
        .dg-card { padding: 8px 10px; margin-bottom: 6px; border-radius: 6px; border: 1px solid #dee2e6; cursor: grab; font-size: 0.85rem; display: flex; justify-content: space-between; align-items: center; transition: box-shadow 0.15s; }
        .dg-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.12); }
        .dg-card.dragging { opacity: 0.5; }
        .dg-column-body.drag-over { background: #e3f2fd; border-radius: 6px; }
        .dg-purpose { font-size: 0.75rem; padding: 2px 8px; border-radius: 10px; color: white; display: inline-block; }
        .badge-purple { background-color: #6f42c1; color: white; }
        .dg-unassigned { min-width: 260px; max-width: 300px; flex-shrink: 0; }
    </style>

    {{-- Validation bar --}}
    @if($canManage && $event->diveGroups->count())
    <div class="d-flex gap-2 mb-3 align-items-center">
        <button class="btn btn-success btn-sm" onclick="validateGroups()">✅ {{ __('Validate All Groups') }}</button>
        <div id="validationResult" class="flex-grow-1"></div>
    </div>
    @endif

    <div class="dg-board">
        {{-- Unassigned pool --}}
        <div class="dg-unassigned">
            <div class="card dc-card">
                <div class="card-header py-2 d-flex justify-content-between">
                    <strong>📋 {{ __('Unassigned') }}</strong>
                    <span class="badge bg-secondary">{{ $unassigned->count() }}</span>
                </div>
                <div class="dg-column-body" id="unassigned" data-group="0">
                    @forelse($unassigned as $reg)
                        @php
                            $uc = $reg->user->certificationLevels->where('category', '!=', 'specialty')->sortByDesc('rank')->first();
                            $rank = $uc?->rank ?? 0;
                        @endphp
                        <div class="dg-card" style="background:{{ rankColor($rank) }}" draggable="{{ $canManage ? 'true' : 'false' }}" data-user-id="{{ $reg->user_id }}">
                            <div>
                                <strong>{{ $reg->user->detail?->first_name }} {{ $reg->user->detail?->last_name }}</strong>
                                <br>
                                @if($uc)
                                    <span class="badge bg-{{ rankBadge($rank) }} {{ $rank >= 70 && $rank < 100 ? 'text-dark' : '' }}" style="font-size:0.7rem">{{ $uc->code }} ({{ $uc->federation?->acronym }})</span>
                                @else
                                    <span class="badge bg-danger" style="font-size:0.7rem">{{ __('No cert') }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-muted small text-center py-2">{{ __('All assigned') }} ✓</div>
                    @endforelse
                </div>
            </div>

            {{-- Create new group --}}
            @if($canManage)
            <div class="card dc-card mt-2">
                <div class="card-body py-2">
                    <form method="POST" action="{{ route('events.dive-groups.store', $event) }}">
                        @csrf
                        <input type="text" name="name" class="form-control form-control-sm mb-1" placeholder="{{ __('Group name') }}">
                        <div class="d-flex gap-1 mb-1">
                            <select name="dive_mode" class="form-select form-select-sm" required>
                                <option value="supervised">{{ __('Supervised') }}</option>
                                <option value="autonomous">{{ __('Autonomous') }}</option>
                                <option value="training">{{ __('Training') }}</option>
                                <option value="certification">{{ __('Certification') }}</option>
                            </select>
                            <input type="number" name="planned_depth" class="form-control form-control-sm" placeholder="m" style="width:60px" min="1">
                        </div>
                        <select name="purpose" class="form-select form-select-sm mb-1">
                            <option value="">{{ __('Purpose…') }}</option>
                            @foreach($purposes as $k => $p)
                                <option value="{{ $k }}">{{ $p['icon'] }} {{ $p['label'] }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-primary btn-sm w-100">+ {{ __('New Group') }}</button>
                    </form>
                </div>
            </div>
            @endif
        </div>

        {{-- Dive group columns --}}
        @foreach($event->diveGroups as $group)
            @php $purposeInfo = $purposes[$group->purpose] ?? null; @endphp
            <div class="dg-column">
                <div class="dg-column-header" style="border-top: 3px solid {{ $purposeInfo['color'] ?? '#6c757d' }}">
                    <div>
                        {{ $group->name }}
                        <br>
                        <span class="badge bg-{{ match($group->dive_mode) { 'supervised' => 'primary', 'autonomous' => 'success', 'training' => 'warning text-dark', 'certification' => 'danger', default => 'secondary' } }}" style="font-size:0.7rem">{{ ucfirst($group->dive_mode) }}</span>
                        @if($group->planned_depth)<span class="badge bg-info" style="font-size:0.7rem">{{ $group->planned_depth }}m</span>@endif
                        @if($purposeInfo)
                            <span class="dg-purpose" style="background:{{ $purposeInfo['color'] }}">{{ $purposeInfo['icon'] }} {{ $purposeInfo['label'] }}</span>
                        @endif
                    </div>
                    @if($canManage)
                        <form method="POST" action="{{ route('dive-groups.destroy', $group) }}" onsubmit="return confirm('{{ __('Delete?') }}')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger py-0 px-1">✕</button>
                        </form>
                    @endif
                </div>
                <div class="dg-column-body" id="group-{{ $group->id }}" data-group="{{ $group->id }}">
                    @if($group->notes)<div class="small text-muted mb-1 px-1">{{ $group->notes }}</div>@endif
                    @foreach($group->members->sortByDesc('role') as $m)
                        @php
                            $cert = $m->user->certificationLevels->where('category', '!=', 'specialty')->sortByDesc('rank')->first();
                            $rank = $cert?->rank ?? 0;
                        @endphp
                        <div class="dg-card" style="background:{{ rankColor($rank) }}" draggable="{{ $canManage ? 'true' : 'false' }}" data-user-id="{{ $m->user_id }}" data-member-id="{{ $m->id }}">
                            <div>
                                @if($m->role === 'leader')👑 @endif
                                <strong>{{ $m->user->detail?->first_name }} {{ $m->user->detail?->last_name }}</strong>
                                <br>
                                @if($cert)
                                    <span class="badge bg-{{ rankBadge($rank) }} {{ $rank >= 70 && $rank < 100 ? 'text-dark' : '' }}" style="font-size:0.7rem">{{ $cert->code }} ({{ $cert->federation?->acronym }})</span>
                                @else
                                    <span class="badge bg-danger" style="font-size:0.7rem">{{ __('No cert') }}</span>
                                @endif
                                <span class="text-muted" style="font-size:0.65rem">{{ $m->role === 'leader' ? __('Leader') : __('Diver') }}</span>
                            </div>
                            @if($canManage)
                                <form method="POST" action="{{ route('dive-groups.remove-member', $m) }}" class="ms-1">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger py-0 px-1" style="font-size:0.65rem">✕</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
                {{-- Quick add --}}
                @if($canManage && $unassigned->count())
                    <div class="p-2 border-top">
                        <form method="POST" action="{{ route('dive-groups.add-member', $group) }}" class="d-flex gap-1">
                            @csrf
                            <select name="user_id" class="form-select form-select-sm" required>
                                <option value="">+</option>
                                @foreach($unassigned as $reg)
                                    @php $uc2 = $reg->user->certificationLevels->where('category', '!=', 'specialty')->sortByDesc('rank')->first(); @endphp
                                    <option value="{{ $reg->user_id }}">{{ $reg->user->detail?->first_name }} {{ $reg->user->detail?->last_name }}{{ $uc2 ? ' — ' . $uc2->code : '' }}</option>
                                @endforeach
                            </select>
                            <select name="role" class="form-select form-select-sm" style="width:80px">
                                <option value="diver">🤿</option>
                                <option value="leader">👑</option>
                            </select>
                            <button class="btn btn-sm btn-primary px-2">+</button>
                        </form>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Legend --}}
    <div class="card dc-card mt-3">
        <div class="card-body py-2 d-flex flex-wrap gap-3" style="font-size:0.8rem">
            <strong>{{ __('Level colors') }}:</strong>
            <span style="background:#f8d7da;padding:2px 8px;border-radius:4px">{{ __('No cert') }}</span>
            <span style="background:#d4edda;padding:2px 8px;border-radius:4px">1★ / OWD</span>
            <span style="background:#cce5ff;padding:2px 8px;border-radius:4px">2★ / AOWD</span>
            <span style="background:#d1ecf1;padding:2px 8px;border-radius:4px">3★ / DM</span>
            <span style="background:#fff3cd;padding:2px 8px;border-radius:4px">4★ / GP</span>
            <span style="background:#e2d5f1;padding:2px 8px;border-radius:4px">{{ __('Instructor') }}</span>
        </div>
    </div>

    @if($canManage)
    <script>
    // Drag & drop between columns
    document.querySelectorAll('.dg-card[draggable="true"]').forEach(card => {
        card.addEventListener('dragstart', e => {
            e.dataTransfer.setData('text/plain', JSON.stringify({
                userId: card.dataset.userId,
                memberId: card.dataset.memberId || null,
                fromGroup: card.closest('[data-group]').dataset.group
            }));
            card.classList.add('dragging');
        });
        card.addEventListener('dragend', () => card.classList.remove('dragging'));
    });

    document.querySelectorAll('.dg-column-body').forEach(col => {
        col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('drag-over'); });
        col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
        col.addEventListener('drop', e => {
            e.preventDefault();
            col.classList.remove('drag-over');
            const data = JSON.parse(e.dataTransfer.getData('text/plain'));
            const toGroup = col.dataset.group;
            if (data.fromGroup === toGroup) return;

            // Remove from old group first, then add to new
            const actions = [];
            if (data.memberId) {
                actions.push(fetch('{{ url("/dive-group-members") }}/' + data.memberId, {
                    method: 'DELETE',
                    headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
                }));
            }

            if (toGroup !== '0') {
                // Small delay to let delete complete
                setTimeout(() => {
                    const form = new FormData();
                    form.append('_token', '{{ csrf_token() }}');
                    form.append('user_id', data.userId);
                    form.append('role', 'diver');
                    fetch('{{ url("/dive-groups") }}/' + toGroup + '/members', {
                        method: 'POST', body: form
                    }).then(() => location.reload());
                }, data.memberId ? 300 : 0);
            } else {
                // Moving back to unassigned = just remove
                if (data.memberId) setTimeout(() => location.reload(), 300);
            }
        });
    });

    function validateGroups() {
        const result = document.getElementById('validationResult');
        result.innerHTML = '<span class="text-muted small">{{ __("Checking…") }}</span>';
        fetch('{{ route("events.dive-groups.validate", $event) }}')
            .then(r => r.json())
            .then(data => {
                if (data.valid) {
                    result.innerHTML = '<span class="badge bg-success">✅ {{ __("All groups comply with rules.") }}</span>';
                } else {
                    let html = '<div class="alert alert-danger py-1 px-2 mb-0 small">';
                    for (const [group, issues] of Object.entries(data.violations)) {
                        html += '<strong>' + group + ':</strong> ' + issues.join('; ') + '<br>';
                    }
                    result.innerHTML = html + '</div>';
                }
            });
    }
    </script>
    @endif
</x-layout>
