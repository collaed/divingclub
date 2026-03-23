{{-- Homepage with configurable widget layout | ClubCEP.eu --}}
<x-layout :title="__('Home')">

    @if($isAdmin)
        <div class="d-flex justify-content-end mb-2">
            <button id="editToggle" class="btn btn-sm btn-outline-secondary" onclick="toggleEditMode()">@icon('⚙️') {{ __('Edit Layout') }}</button>
        </div>
    @endif

    {{-- Top zone (full width) --}}
    <div id="zone-top" data-zone="top">
        @foreach($widgets->where('zone', 'top') as $i => $widget)
            @if($widget['enabled'] && !($widget['hidden_by_role'] ?? false))
                <div class="hp-widget" data-index="{{ $i }}" data-type="{{ $widget['type'] }}">
                    <div class="hp-widget-bar d-none">
                        <span class="hp-drag-handle" title="Drag">⠿</span>
                        <span class="badge bg-secondary">{{ $widgetTypes[$widget['type']]['icon'] ?? '' }} {{ $widgetTypes[$widget['type']]['label'] ?? $widget['type'] }}</span>
                        <select class="form-select form-select-sm hp-visibility" style="width:auto;font-size:.75rem" data-index="{{ $i }}">
                            @foreach(['public' => '🌍  Public', 'members' => '👥  Members', 'instructors' => '🎓  Instructors', 'bureau' => '🔒  Bureau'] as $v => $label)
                                <option value="{{ $v }}" {{ ($widget['visibility'] ?? 'public') === $v ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if(in_array($widget['type'], ['articles', 'photos', 'upcoming_events', 'hero']))
                            <button class="btn btn-sm btn-outline-secondary hp-config-btn" data-index="{{ $i }}" title="{{ __('Settings') }}">⚙</button>
                        @endif
                        <button class="btn btn-sm btn-outline-danger ms-auto hp-remove" title="{{ __('Hide') }}">✕</button>
                    </div>
                    @include('home._' . $widget['type'], ['widget' => $widget, 'zone' => 'top'])
                </div>
            @endif
        @endforeach
    </div>

    <div class="row">
        {{-- Main zone --}}
        <div class="col-lg-8">
            <div id="zone-main" data-zone="main">
                @foreach($widgets->where('zone', 'main') as $i => $widget)
                    @if($widget['enabled'] && !($widget['hidden_by_role'] ?? false))
                        <div class="hp-widget" data-index="{{ $i }}" data-type="{{ $widget['type'] }}">
                            <div class="hp-widget-bar d-none">
                                <span class="hp-drag-handle" title="Drag">⠿</span>
                                <span class="badge bg-secondary">{{ $widgetTypes[$widget['type']]['icon'] ?? '' }} {{ $widgetTypes[$widget['type']]['label'] ?? $widget['type'] }}</span>
                                <select class="form-select form-select-sm hp-visibility" style="width:auto;font-size:.75rem" data-index="{{ $i }}">
                                    @foreach(['public' => '🌍  Public', 'members' => '👥  Members', 'instructors' => '🎓  Instructors', 'bureau' => '🔒  Bureau'] as $v => $label)
                                        <option value="{{ $v }}" {{ ($widget['visibility'] ?? 'public') === $v ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if(in_array($widget['type'], ['articles', 'photos', 'upcoming_events', 'hero']))
                                    <button class="btn btn-sm btn-outline-secondary hp-config-btn" data-index="{{ $i }}" title="{{ __('Settings') }}">⚙</button>
                                @endif
                                <button class="btn btn-sm btn-outline-danger ms-auto hp-remove" title="{{ __('Hide') }}">✕</button>
                            </div>
                            @include('home._' . $widget['type'], ['widget' => $widget, 'zone' => 'main'])
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Sidebar zone --}}
        <div class="col-lg-4">
            <div id="zone-sidebar" data-zone="sidebar">
                @foreach($widgets->where('zone', 'sidebar') as $i => $widget)
                    @if($widget['enabled'] && !($widget['hidden_by_role'] ?? false))
                        <div class="hp-widget" data-index="{{ $i }}" data-type="{{ $widget['type'] }}">
                            <div class="hp-widget-bar d-none">
                                <span class="hp-drag-handle" title="Drag">⠿</span>
                                <span class="badge bg-secondary">{{ $widgetTypes[$widget['type']]['icon'] ?? '' }} {{ $widgetTypes[$widget['type']]['label'] ?? $widget['type'] }}</span>
                                <select class="form-select form-select-sm hp-visibility" style="width:auto;font-size:.75rem" data-index="{{ $i }}">
                                    @foreach(['public' => '🌍  Public', 'members' => '👥  Members', 'instructors' => '🎓  Instructors', 'bureau' => '🔒  Bureau'] as $v => $label)
                                        <option value="{{ $v }}" {{ ($widget['visibility'] ?? 'public') === $v ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if(in_array($widget['type'], ['articles', 'photos', 'upcoming_events', 'hero']))
                                    <button class="btn btn-sm btn-outline-secondary hp-config-btn" data-index="{{ $i }}" title="{{ __('Settings') }}">⚙</button>
                                @endif
                                <button class="btn btn-sm btn-outline-danger ms-auto hp-remove" title="{{ __('Hide') }}">✕</button>
                            </div>
                            @include('home._' . $widget['type'], ['widget' => $widget, 'zone' => 'sidebar'])
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    @if($isAdmin)
    {{-- Disabled widgets panel (shown in edit mode) --}}
    <div id="disabledPanel" class="d-none mt-3">
        <div class="card border-dashed">
            <div class="card-header py-2 bg-light">@icon('📦') {{ __('Available Widgets (click to add)') }}</div>
            <div class="card-body d-flex flex-wrap gap-2" id="disabledList"></div>
        </div>
    </div>

    <style>
    .hp-editing .hp-widget { outline: 2px dashed #ccc; outline-offset: 2px; margin-bottom: 12px; position: relative; }
    .hp-editing .hp-widget:hover { outline-color: #0066cc; }
    .hp-editing .hp-widget-bar { display: flex !important; align-items: center; gap: 6px; padding: 4px 8px; background: #f0f0f0; border-bottom: 1px solid #ddd; font-size: 0.8rem; }
    .hp-drag-handle { cursor: grab; font-size: 1.1rem; user-select: none; }
    .hp-widget.dragging { opacity: 0.4; }
    .hp-drop-indicator { height: 4px; background: #0066cc; margin: 4px 0; border-radius: 2px; }
    </style>

    <script>
    let editMode = false;
    let layout = @json($widgets->values());
    const widgetTypes = @json($widgetTypes);
    const saveUrl = '{{ route("admin.homepage-layout.save") }}';
    const csrf = '{{ csrf_token() }}';

    function toggleEditMode() {
        editMode = !editMode;
        document.body.classList.toggle('hp-editing', editMode);
        document.getElementById('editToggle').innerHTML = editMode ? '💾 {{ __("Save & Close") }}' : '⚙️ {{ __("Edit Layout") }}';
        document.getElementById('disabledPanel').classList.toggle('d-none', !editMode);

        // Show/hide widget bars
        document.querySelectorAll('.hp-widget-bar').forEach(b => b.classList.toggle('d-none', !editMode));

        if (editMode) {
            enableDragDrop();
            renderDisabledWidgets();
        } else {
            saveLayout();
        }
    }

    function enableDragDrop() {
        document.querySelectorAll('.hp-widget').forEach(w => {
            w.draggable = true;
            w.addEventListener('dragstart', e => {
                e.dataTransfer.setData('text/plain', w.dataset.index);
                w.classList.add('dragging');
            });
            w.addEventListener('dragend', () => w.classList.remove('dragging'));
        });

        document.querySelectorAll('[data-zone]').forEach(zone => {
            zone.addEventListener('dragover', e => { e.preventDefault(); });
            zone.addEventListener('drop', e => {
                e.preventDefault();
                const fromIdx = parseInt(e.dataTransfer.getData('text/plain'));
                const target = e.target.closest('.hp-widget');
                const zoneEl = e.target.closest('[data-zone]');
                if (!zoneEl) return;

                const widget = layout[fromIdx];
                widget.zone = zoneEl.dataset.zone;

                // Move in DOM
                const dragged = document.querySelector(`.hp-widget[data-index="${fromIdx}"]`);
                if (target && target !== dragged) {
                    const rect = target.getBoundingClientRect();
                    const mid = rect.top + rect.height / 2;
                    if (e.clientY < mid) target.before(dragged);
                    else target.after(dragged);
                } else {
                    zoneEl.appendChild(dragged);
                }

                rebuildLayoutFromDOM();
            });
        });

        // Remove buttons
        document.querySelectorAll('.hp-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                const w = btn.closest('.hp-widget');
                const idx = parseInt(w.dataset.index);
                layout[idx].enabled = false;
                w.remove();
                renderDisabledWidgets();
            });
        });

        // Visibility selectors
        document.querySelectorAll('.hp-visibility').forEach(sel => {
            sel.addEventListener('change', () => {
                const idx = parseInt(sel.dataset.index);
                layout[idx].visibility = sel.value;
            });
        });

        // Config buttons — show inline config panel
        document.querySelectorAll('.hp-config-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const w = btn.closest('.hp-widget');
                let panel = w.querySelector('.hp-config-panel');
                if (panel) { panel.remove(); return; }

                const idx = parseInt(btn.dataset.index);
                const widget = layout[idx];
                const cfg = widget.config || {};
                const type = widget.type;

                panel = document.createElement('div');
                panel.className = 'hp-config-panel p-2 bg-light border-bottom small';

                let html = '<div class="d-flex flex-wrap gap-2 align-items-center">';
                if (type === 'articles' || type === 'upcoming_events') {
                    html += `<label>{{ __('Items') }}: <input type="number" class="form-control form-control-sm d-inline-block" style="width:70px" data-cfg="limit" value="${cfg.limit || (type === 'articles' ? 10 : 5)}" min="1" max="50"></label>`;
                }
                if (type === 'photos' || type === 'hero') {
                    html += `<label>{{ __('Photos') }}: <input type="number" class="form-control form-control-sm d-inline-block" style="width:70px" data-cfg="count" value="${cfg.count || 8}" min="1" max="30"></label>`;
                }
                html += '</div>';
                panel.innerHTML = html;

                // Insert after the bar
                const bar = w.querySelector('.hp-widget-bar');
                bar.after(panel);

                // Listen for changes
                panel.querySelectorAll('[data-cfg]').forEach(input => {
                    input.addEventListener('change', () => {
                        if (!layout[idx].config) layout[idx].config = {};
                        layout[idx].config[input.dataset.cfg] = parseInt(input.value) || input.value;
                    });
                });
            });
        });
    }

    function rebuildLayoutFromDOM() {
        const newLayout = [];
        ['top', 'main', 'sidebar'].forEach(zone => {
            const el = document.getElementById('zone-' + zone);
            el.querySelectorAll('.hp-widget').forEach(w => {
                const idx = parseInt(w.dataset.index);
                layout[idx].zone = zone;
                newLayout.push(layout[idx]);
            });
        });
        // Add disabled widgets at the end
        layout.filter(w => !w.enabled).forEach(w => newLayout.push(w));
        layout = newLayout;
        // Re-index
        document.querySelectorAll('.hp-widget').forEach((w, i) => w.dataset.index = layout.indexOf(layout.find(l => l.type === w.dataset.type && l.enabled)));
    }

    function renderDisabledWidgets() {
        const list = document.getElementById('disabledList');
        const disabled = layout.filter(w => !w.enabled);
        if (!disabled.length) {
            list.innerHTML = '<span class="text-muted small">{{ __("All widgets are active.") }}</span>';
            return;
        }
        list.innerHTML = disabled.map(w => {
            const meta = widgetTypes[w.type] || {};
            return `<button class="btn btn-sm btn-outline-primary" onclick="enableWidget('${w.type}')">${meta.icon || ''} ${meta.label || w.type}</button>`;
        }).join('');
    }

    function enableWidget(type) {
        const w = layout.find(l => l.type === type && !l.enabled);
        if (!w) return;
        w.enabled = true;
        // Reload page to render the widget (server-side rendering needed)
        saveLayout().then(() => location.reload());
    }

    function saveLayout() {
        rebuildLayoutFromDOM();
        return fetch(saveUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
            body: JSON.stringify({layout: layout})
        });
    }
    </script>
    @endif
</x-layout>
