<!-- Trello-style dive group planner: drag-drop assignment, rule validation, auto-propose fiche de sécurité | ClubCEP.eu -->
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

    {{-- Board layout: Trello-style horizontal columns with drag-drop support --}}
    <style>
        .dg-board { display: flex; gap: 16px; overflow-x: auto; padding-bottom: 16px; align-items: flex-start; }
        .dg-column { min-width: 260px; max-width: 300px; flex-shrink: 0; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; }
        .dg-column-header { padding: 10px 12px; font-weight: 600; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        .dg-column-body { padding: 8px; min-height: 80px; }
        .dg-card { padding: 8px 10px; margin-bottom: 6px; border-radius: 6px; border: 1px solid #dee2e6; cursor: grab; font-size: 0.85rem; display: flex; justify-content: space-between; align-items: center; transition: box-shadow 0.15s; user-select: none; -webkit-user-select: none; }
        .dg-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.12); }
        .dg-card .drag-handle { cursor: grab; padding: 0 6px; font-size: 1rem; color: #aaa; flex-shrink: 0; }
        .dg-card .drag-handle:hover { color: #333; }
        .dg-card.dragging { opacity: 0.5; }
        .dg-column-body.drag-over { background: #e3f2fd; border-radius: 6px; }
        .dg-purpose { font-size: 0.75rem; padding: 2px 8px; border-radius: 10px; color: white; display: inline-block; }
        .badge-purple { background-color: #6f42c1; color: white; }
        .dg-unassigned { min-width: 260px; max-width: 300px; flex-shrink: 0; }
    </style>

    {{-- Stale groups warning: registrations changed since groups were last edited --}}
    @if($groupsStale && $event->diveGroups->count())
        <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3">
            <span>⚠️ {{ __('Registrations have changed since groups were last edited. Some participants may be unassigned or cancelled.') }}</span>
            <button class="btn btn-warning btn-sm" onclick="proposeGroups()">🔄 {{ __('Reprocess') }}</button>
        </div>
    @endif

    {{-- Toolbar: validate groups against federation rules + auto-propose --}}
    @if($canManage)
    <div class="d-flex gap-2 mb-3 align-items-center flex-wrap">
        @if($event->diveGroups->count())
            <button class="btn btn-success btn-sm" onclick="validateGroups()">✅ {{ __('Validate All Groups') }}</button>
            <a href="{{ route('events.dive-groups.print', $event) }}" class="btn btn-outline-secondary btn-sm" target="_blank">🖨️ {{ __('Print Fiche PDF') }}</a>
        @endif
        <div class="input-group input-group-sm" style="width:auto">
            <span class="input-group-text">{{ __('Max depth') }}</span>
            <input type="number" id="proposeDepth" class="form-control" value="{{ $event->diveSite?->max_depth ?? 20 }}" min="1" max="60" style="width:60px">
            <span class="input-group-text">m</span>
            <button class="btn btn-primary" onclick="proposeGroups()">🤖 {{ __('Auto-propose (Fiche de Sécurité)') }}</button>
        </div>
        <div id="validationResult" class="flex-grow-1"></div>
    </div>

    {{-- Proposal preview (hidden until generated) --}}
    <div id="proposalPreview" class="d-none mb-3">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white d-flex justify-content-between">
                <span>📋 {{ __('Proposed Fiche de Sécurité') }}</span>
                <div>
                    <button class="btn btn-sm btn-light" onclick="applyProposal()">✅ {{ __('Apply') }}</button>
                    <button class="btn btn-sm btn-outline-light" onclick="document.getElementById('proposalPreview').classList.add('d-none')">✕</button>
                </div>
            </div>
            <div class="card-body">
                <div id="proposalWarnings"></div>
                <div id="proposalBoard" class="dg-board"></div>
            </div>
        </div>
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
                            @if($canManage)<span class="drag-handle" title="{{ __('Drag') }}">⠿</span>@endif
                            <div style="flex:1;min-width:0">
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
                            @if($canManage)<span class="drag-handle" title="{{ __('Drag') }}">⠿</span>@endif
                            <div style="flex:1;min-width:0">
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
    // Drag & drop between columns — use event delegation on the board
    // so dynamically added cards also work
    const board = document.querySelector('.dg-board');

    board.addEventListener('dragstart', e => {
        const card = e.target.closest('.dg-card[draggable="true"]');
        if (!card) return;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', JSON.stringify({
            userId: card.dataset.userId,
            memberId: card.dataset.memberId || null,
            fromGroup: card.closest('[data-group]').dataset.group
        }));
        card.classList.add('dragging');
    });

    board.addEventListener('dragend', e => {
        const card = e.target.closest('.dg-card');
        if (card) card.classList.remove('dragging');
    });

    // Drop zones: all elements with data-group attribute
    document.querySelectorAll('[data-group]').forEach(col => {
        col.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; col.classList.add('drag-over'); });
        col.addEventListener('dragleave', e => {
            // Only remove highlight when actually leaving the column (not entering a child)
            if (!col.contains(e.relatedTarget)) col.classList.remove('drag-over');
        });
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

    // ── Auto-propose (fiche de sécurité) ──────────────────────
    let currentProposal = null;

    function rankColorJS(rank) {
        if (!rank) return '#f8d7da';
        if (rank <= 20) return '#d4edda';
        if (rank <= 45) return '#cce5ff';
        if (rank <= 69) return '#d1ecf1';
        if (rank <= 99) return '#fff3cd';
        return '#e2d5f1';
    }

    // Render a draggable card for the proposal board
    function proposalCard(p, isLeader) {
        return '<div class="dg-card" draggable="true" data-user-id="' + p.user_id + '" data-rank="' + p.rank + '" data-cert="' + p.cert_code + '" data-name="' + p.name + '" style="background:' + rankColorJS(p.rank) + '">' +
            '<span class="drag-handle">⠿</span>' +
            '<div style="flex:1;min-width:0">' + (isLeader ? '👑 ' : '') +
            '<strong>' + p.name + '</strong><br>' +
            '<span class="badge bg-info" style="font-size:0.7rem">' + p.cert_code + '</span>' +
            (isLeader ? ' <span class="text-muted" style="font-size:0.65rem">{{ __("Leader") }}</span>' : '') +
            '</div></div>';
    }

    // Wire drag-drop on the proposal board (called after rendering)
    function wireProposalDragDrop() {
        const pBoard = document.getElementById('proposalBoard');

        pBoard.querySelectorAll('.dg-card[draggable="true"]').forEach(card => {
            card.addEventListener('dragstart', e => {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', JSON.stringify({
                    userId: card.dataset.userId,
                    fromProposalGroup: card.closest('[data-proposal-group]')?.dataset.proposalGroup || 'unassigned'
                }));
                card.classList.add('dragging');
            });
            card.addEventListener('dragend', () => card.classList.remove('dragging'));
        });

        pBoard.querySelectorAll('[data-proposal-group]').forEach(col => {
            col.addEventListener('dragover', e => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                col.classList.add('drag-over');
            });
            col.addEventListener('dragleave', e => { if (!col.contains(e.relatedTarget)) col.classList.remove('drag-over'); });
            col.addEventListener('drop', e => {
                e.preventDefault();
                col.classList.remove('drag-over');
                const data = JSON.parse(e.dataTransfer.getData('text/plain'));
                const card = pBoard.querySelector('.dg-card[data-user-id="' + data.userId + '"]');
                if (!card) return;

                // Find the card we're dropping onto to insert before it
                const target = e.target.closest('.dg-card');
                if (target && target !== card && col.contains(target)) {
                    col.insertBefore(card, target);
                } else {
                    col.appendChild(card);
                }

                updateLeaderBadges();
            });
        });
    }

    // First card in each proposal group column is the leader
    function updateLeaderBadges() {
        document.getElementById('proposalBoard').querySelectorAll('[data-proposal-group]').forEach(col => {
            if (col.dataset.proposalGroup === 'unassigned') return;
            col.querySelectorAll('.dg-card').forEach((card, i) => {
                const div = card.querySelector('div');
                const name = card.dataset.name;
                const cert = card.dataset.cert;
                const isLeader = (i === 0);
                div.innerHTML = (isLeader ? '👑 ' : '') +
                    '<strong>' + name + '</strong><br>' +
                    '<span class="badge bg-info" style="font-size:0.7rem">' + cert + '</span>' +
                    (isLeader ? ' <span class="text-muted" style="font-size:0.65rem">{{ __("Leader") }}</span>' : '');
            });
        });
    }

    function proposeGroups() {
        const depth = document.getElementById('proposeDepth').value;
        const preview = document.getElementById('proposalPreview');
        const pBoard = document.getElementById('proposalBoard');
        const warnings = document.getElementById('proposalWarnings');

        pBoard.innerHTML = '<span class="text-muted">{{ __("Calculating…") }}</span>';
        warnings.innerHTML = '';
        preview.classList.remove('d-none');

        fetch('{{ route("events.dive-groups.propose", $event) }}?max_depth=' + depth)
            .then(r => r.json())
            .then(data => {
                currentProposal = data;

                if (data.warnings.length) {
                    warnings.innerHTML = '<div class="alert alert-warning py-1 small mb-2">' +
                        data.warnings.join('<br>') + '</div>';
                }

                let html = '';

                // Unassigned column (droppable too — park people here)
                html += '<div class="dg-column"><div class="dg-column-header">📋 {{ __("Unassigned") }}</div>' +
                    '<div class="dg-column-body" data-proposal-group="unassigned">';
                (data.unassigned || []).forEach(p => { html += proposalCard(p, false); });
                html += '</div></div>';

                // Group columns
                data.groups.forEach((g, i) => {
                    const modeColors = {supervised:'primary', autonomous:'success', training:'warning', certification:'danger'};
                    html += '<div class="dg-column" data-gname="' + g.name + '" data-gmode="' + g.dive_mode + '" data-gdepth="' + (g.planned_depth||'') + '">' +
                        '<div class="dg-column-header">' + g.name +
                        '<br><span class="badge bg-' + (modeColors[g.dive_mode]||'secondary') + '" style="font-size:0.7rem">' + g.dive_mode + '</span>' +
                        ' <span class="badge bg-info" style="font-size:0.7rem">' + (g.planned_depth||'?') + 'm</span></div>' +
                        '<div class="dg-column-body" data-proposal-group="' + i + '">';

                    html += proposalCard(g.leader, true);
                    g.members.forEach(m => { html += proposalCard(m, false); });
                    html += '</div></div>';
                });

                pBoard.innerHTML = html || '<span class="text-muted">{{ __("No groups could be formed.") }}</span>';
                wireProposalDragDrop();
            });
    }

    // Apply reads the current DOM state, not the original proposal object
    function applyProposal() {
        if (!confirm('{{ __("Apply this proposal? Existing groups will be replaced.") }}')) return;

        const pBoard = document.getElementById('proposalBoard');
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("events.dive-groups.apply-proposal", $event) }}';

        const csrf = document.createElement('input');
        csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '{{ csrf_token() }}';
        form.appendChild(csrf);

        // Read groups from DOM — each .dg-column with data-gname is a group
        let gi = 0;
        pBoard.querySelectorAll('.dg-column[data-gname]').forEach(col => {
            const cards = col.querySelectorAll('.dg-card[data-user-id]');
            if (cards.length === 0) return; // skip empty groups

            const addHidden = (name, val) => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = name; inp.value = val;
                form.appendChild(inp);
            };

            addHidden('groups[' + gi + '][name]', col.dataset.gname);
            addHidden('groups[' + gi + '][dive_mode]', col.dataset.gmode);
            addHidden('groups[' + gi + '][planned_depth]', col.dataset.gdepth);

            // First card = leader, rest = members
            cards.forEach((card, ci) => {
                if (ci === 0) {
                    addHidden('groups[' + gi + '][leader_id]', card.dataset.userId);
                } else {
                    addHidden('groups[' + gi + '][member_ids][]', card.dataset.userId);
                }
            });
            gi++;
        });

        if (gi === 0) { alert('{{ __("No groups to apply.") }}'); return; }

        document.body.appendChild(form);
        form.submit();
    }
    </script>
    @endif
</x-layout>
