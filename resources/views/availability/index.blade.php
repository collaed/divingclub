<x-layout :title="__('Instructor Calendar')">
    @php
        $actColors = $colors;
        $dow = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    @endphp

    <style>
    .ic-header { background: linear-gradient(135deg, #00695c, #004d40); color: #fff; padding: 1rem 1.5rem; border-radius: 10px 10px 0 0; margin-bottom: 0; }
    .ic-header h4 { margin: 0; }
    .ic-header a, .ic-header .btn { color: #fff; border-color: rgba(255,255,255,.4); }
    .ic-header a:hover { background: rgba(255,255,255,.15); }
    .ic-table thead { background: #00695c; color: #fff; }
    .ic-table thead th { border-color: #00796b; font-weight: 600; }
    .ic-table td { vertical-align: top; min-width: 100px; min-height: 60px; }
    .ic-today { outline: 2px solid #00bfa5; outline-offset: -2px; background: #e0f2f1 !important; }
    .ic-legend { display: flex; flex-wrap: wrap; gap: .5rem; }
    .ic-legend-item { display: inline-flex; align-items: center; gap: .3rem; font-size: .75rem; padding: .2rem .5rem; border-radius: 4px; }
    .ic-avatar { width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: .6rem; font-weight: 700; color: #fff; }
    .ic-toggle { cursor: pointer; display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 4px; font-size: .75rem; line-height: 1; }
    .ic-toggle-add { background: #28a745; color: #fff; }
    .ic-toggle-remove { background: #dc3545; color: #fff; }

    /* Stamp mode (bureau bulk registration) */
    .ic-stamp-toolbar { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; padding: .5rem .75rem; background: #f1f8f7; border: 1px solid #b2dfdb; border-radius: 6px; margin: .5rem 0; }
    .ic-stamp-chip { cursor: pointer; display: inline-flex; align-items: center; gap: .3rem; font-size: .75rem; padding: .2rem .55rem; border-radius: 14px; border: 2px solid transparent; background: #fff; user-select: none; }
    .ic-stamp-chip .ic-avatar { width: 20px; height: 20px; }
    .ic-stamp-chip.active { border-color: #00695c; box-shadow: 0 0 0 2px rgba(0,105,92,.2); font-weight: 700; }
    .stamp-active .ic-slot { cursor: cell; }
    .stamp-active .ic-slot:hover { outline: 2px dashed #00695c; outline-offset: 1px; }
    .ic-slot.ic-stamp-saving { opacity: .5; }
    /* Floating cursor badge (desktop) */
    #icStampCursor { position: fixed; z-index: 3000; pointer-events: none; transform: translate(8px, 8px); display: none; }
    #icStampCursor .ic-avatar { width: 26px; height: 26px; font-size: .7rem; box-shadow: 0 2px 6px rgba(0,0,0,.3); }
    /* Mobile sticky brush bar */
    #icStampBar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 2900; background: #00695c; color: #fff; padding: .6rem 1rem; display: none; align-items: center; justify-content: space-between; gap: .5rem; box-shadow: 0 -2px 8px rgba(0,0,0,.2); }
    #icStampBar.show { display: flex; }
    #icStampBar .ic-avatar { width: 24px; height: 24px; }
    </style>

    <div class="ic-header d-flex justify-content-between align-items-center">
        <h4>🏊 {{ __('Instructor Planning') }}</h4>
        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('availability.index', ['month' => $start->copy()->subMonth()->format('Y-m')]) }}" class="btn btn-sm btn-outline-light">←</a>
            <span class="fw-bold">{{ $start->translatedFormat('F Y') }}</span>
            <a href="{{ route('availability.index', ['month' => $start->copy()->addMonth()->format('Y-m')]) }}" class="btn btn-sm btn-outline-light">→</a>
        </div>
    </div>

    @if($isInstructor)
        <div class="alert alert-info small py-2 mb-0 rounded-0" style="background:#e0f2f1;border-color:#b2dfdb;color:#004d40">
            💡 {{ __('Click ✓ to mark yourself available. Click ✗ to remove.') }}
        </div>
    @else
        <div class="alert alert-light small py-2 mb-0 rounded-0 border-0 text-muted">
            👁 {{ __('Read-only view — see which instructors are available for each session.') }}
        </div>
    @endif

    @if($isBureau)
        <div class="ic-stamp-toolbar" id="icStampToolbar">
            <strong class="small text-muted me-1">🖊️ {{ __('Stamp mode') }}:</strong>
            <span class="small text-muted me-2">{{ __('Pick an instructor, then click sessions to add/remove them.') }}</span>
            @foreach($instructors as $inst)
                @php
                    $ini = $inst->detail?->instructor_initial ?: mb_strtoupper(mb_substr($inst->detail?->first_name ?? '?', 0, 1));
                    $ic = $inst->detail?->instructor_color ?? '#6c757d';
                    $fullName = trim(($inst->detail?->first_name ?? '').' '.($inst->detail?->last_name ?? ''));
                @endphp
                <span class="ic-stamp-chip" data-stamp-user="{{ $inst->id }}" data-stamp-initial="{{ $ini }}" data-stamp-color="{{ $ic }}" data-stamp-name="{{ $fullName }}">
                    <span class="ic-avatar" style="background:{{ $ic }}">{{ $ini }}</span>{{ $inst->detail?->first_name }}
                </span>
            @endforeach
            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto d-none" id="icStampExit">✕ {{ __('Exit stamp mode') }}</button>
        </div>
    @endif

    <div class="table-responsive">
        <table class="table table-bordered table-sm text-center align-middle ic-table" style="font-size:.85rem">
            <thead>
                <tr>
                    <th style="width:30px">{{ __('Wk') }}</th>
                    @foreach($dow as $d)
                        <th>{{ __($d) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php
                    $cursor = $start->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                    $endWeek = $end->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                    // Index availabilities by event_id for quick lookup
                    $availByEvent = collect();
                    foreach ($availabilities as $dateAvails) {
                        foreach ($dateAvails as $av) {
                            if ($av->event_id) {
                                $availByEvent[$av->event_id] = $availByEvent->get($av->event_id, collect())->push($av);
                            }
                        }
                    }
                @endphp
                @while($cursor->lte($endWeek))
                    <tr>
                        <td class="text-muted small fw-bold">{{ $cursor->weekOfYear }}</td>
                        @for($d = 0; $d < 7; $d++)
                            @php
                                $day = $cursor->copy()->addDays($d);
                                $dateStr = $day->format('Y-m-d');
                                $inMonth = $day->month === $start->month;
                                $isPast = $day->lt(today());
                                $dayEvents = $events[$dateStr] ?? collect();
                                $isWeekend = $day->isWeekend();
                            @endphp
                            <td class="{{ !$inMonth ? 'text-muted bg-light' : '' }} {{ $isWeekend && $inMonth ? 'bg-light' : '' }} {{ $day->isToday() ? 'ic-today' : '' }}" style="vertical-align:top;min-width:100px;height:60px;padding:2px">
                                @if($inMonth)
                                    <div class="fw-bold small {{ $isPast ? 'text-muted' : '' }}">{{ $day->format('d') }}</div>
                                    @if($dayEvents->count() > 1)
                                        {{-- Side by side for multiple events (e.g. Wednesday two timeslots) --}}
                                        <div class="d-flex gap-1">
                                        @foreach($dayEvents->sortBy('event_time') as $ev)
                                            @php
                                                $evAvails = $availByEvent->get($ev->id, collect());
                                                $myAvail = $evAvails->firstWhere('user_id', auth()->id());
                                                $actType = $ev->event_type ?? 'pool';
                                                $actColor = $actColors[$actType]['color'] ?? ($ev->color_hex ?? '#6c757d');
                                                $actText = $actColors[$actType]['text'] ?? '#fff';
                                            @endphp
                                            <div class="flex-fill rounded px-1 text-start activity-{{ $actType }} ic-slot" data-event-id="{{ $ev->id }}" style="font-size:.6rem;min-width:0">
                                                <div class="d-flex align-items-center gap-1">
                                                    <a href="{{ route('events.show', $ev) }}" class="text-truncate text-decoration-none flex-grow-1" style="color:{{ $actText }};max-width:50px" title="{{ $ev->title }}{{ $ev->event_time ? ' · '.Str::substr($ev->event_time, 0, 5) : '' }}">{{ Str::limit($ev->title, 8) }}</a>
                                                    @if($isInstructor && !$isPast)
                                                        <span class="ms-auto ic-toggle {{ $myAvail ? 'ic-toggle-remove' : 'ic-toggle-add' }}" data-toggle-event="{{ $ev->id }}" title="{{ $myAvail ? __('Remove availability') : __('Mark available') }}">{{ $myAvail ? '✗' : '✓' }}</span>
                                                    @endif
                                                </div>
                                                <span class="d-block ic-avatars" data-event-id="{{ $ev->id }}" style="font-size:.55rem;letter-spacing:1px">@foreach($evAvails as $av)@php
                                                        $ini = $av->user->detail?->instructor_initial ?: mb_strtoupper(mb_substr($av->user->detail?->first_name ?? '?', 0, 1));
                                                        $ic = $av->user->detail?->instructor_color ?? '#00695c';
                                                    @endphp<span class="ic-avatar" data-user-id="{{ $av->user_id }}" style="background:{{ $ic }}" title="{{ $av->user->detail?->first_name }} {{ $av->user->detail?->last_name }}">{{ $ini }}</span> @endforeach</span>
                                            </div>
                                        @endforeach
                                        </div>
                                    @elseif($dayEvents->count() === 1)
                                        {{-- Single event — full width --}}
                                        @foreach($dayEvents as $ev)
                                            @php
                                                $evAvails = $availByEvent->get($ev->id, collect());
                                                $myAvail = $evAvails->firstWhere('user_id', auth()->id());
                                                $actType = $ev->event_type ?? 'pool';
                                                $actColor = $actColors[$actType]['color'] ?? ($ev->color_hex ?? '#6c757d');
                                                $actText = $actColors[$actType]['text'] ?? '#fff';
                                            @endphp
                                            <div class="d-block mb-1 rounded px-1 text-start activity-{{ $actType }} ic-slot" data-event-id="{{ $ev->id }}" style="font-size:.65rem">
                                                <div class="d-flex align-items-center gap-1">
                                                    <a href="{{ route('events.show', $ev) }}" class="text-truncate text-decoration-none flex-grow-1" style="color:{{ $actText }};max-width:70px" title="{{ $ev->title }}{{ $ev->event_time ? ' · '.Str::substr($ev->event_time, 0, 5) : '' }}">{{ Str::limit($ev->title, 12) }}</a>
                                                    @if($isInstructor && !$isPast)
                                                        <span class="ms-auto ic-toggle {{ $myAvail ? 'ic-toggle-remove' : 'ic-toggle-add' }}" data-toggle-event="{{ $ev->id }}" title="{{ $myAvail ? __('Remove availability') : __('Mark available') }}">{{ $myAvail ? '✗' : '✓' }}</span>
                                                    @endif
                                                </div>
                                                <span class="d-block ic-avatars" data-event-id="{{ $ev->id }}" style="font-size:.6rem;letter-spacing:1px">@foreach($evAvails as $av)@php
                                                        $ini = $av->user->detail?->instructor_initial ?: mb_strtoupper(mb_substr($av->user->detail?->first_name ?? '?', 0, 1));
                                                        $ic = $av->user->detail?->instructor_color ?? '#00695c';
                                                    @endphp<span class="ic-avatar" data-user-id="{{ $av->user_id }}" style="background:{{ $ic }}" title="{{ $av->user->detail?->first_name }} {{ $av->user->detail?->last_name }}">{{ $ini }}</span> @endforeach</span>
                                            </div>
                                        @endforeach
                                    @endif
                                    @if($dayEvents->isEmpty() && !$isPast)
                                        <span class="text-muted" style="font-size:.6rem">—</span>
                                    @endif
                                @endif
                            </td>
                        @endfor
                    </tr>
                    @php $cursor->addWeek(); @endphp
                @endwhile
            </tbody>
        </table>
    </div>

    {{-- Activity type legend --}}
    <div class="mt-3 mb-2">
        <strong class="small text-muted">{{ __('Activity Types') }}:</strong>
        <div class="ic-legend mt-1">
            @foreach($actColors as $key => $ac)
                <span class="ic-legend-item" style="background:{{ $ac['color'] }};color:{{ $ac['text'] }}">{{ $ac['icon'] }} {{ __($ac['label']) }}</span>
            @endforeach
        </div>
    </div>

    {{-- Instructor initials legend --}}
    <div class="mt-3">
        <strong class="small text-muted">{{ __('Instructors') }}:</strong>
        <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-1 mt-1">
            @foreach($instructors->filter(fn($u) => $u->hasAnyRole(['instructor', 'instructor_apnea'])) as $inst)
                @php
                    $ini = $inst->detail?->instructor_initial ?: mb_strtoupper(mb_substr($inst->detail?->first_name ?? '?', 0, 1));
                    $ic = $inst->detail?->instructor_color ?? '#6c757d';
                @endphp
                <div class="col small"><span class="badge me-1" style="background:{{ $ic }};color:#fff">{{ $ini }}</span>{{ $inst->detail?->first_name }} {{ $inst->detail?->last_name }}</div>
            @endforeach
        </div>
    </div>
    @if($instructors->filter(fn($u) => $u->hasAnyRole(['bureau_master', 'bureau_technical', 'bureau_finance']) && !$u->hasAnyRole(['instructor', 'instructor_apnea']))->isNotEmpty())
    <div class="mt-2">
        <strong class="small text-muted">{{ __('Bureau') }}:</strong>
        <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-1 mt-1">
            @foreach($instructors->filter(fn($u) => $u->hasAnyRole(['bureau_master', 'bureau_technical', 'bureau_finance']) && !$u->hasAnyRole(['instructor', 'instructor_apnea'])) as $inst)
                @php
                    $ini = $inst->detail?->instructor_initial ?: mb_strtoupper(mb_substr($inst->detail?->first_name ?? '?', 0, 1));
                    $ic = $inst->detail?->instructor_color ?? '#6c757d';
                @endphp
                <div class="col small"><span class="badge me-1" style="background:{{ $ic }};color:#fff;opacity:.7">{{ $ini }}</span>{{ $inst->detail?->first_name }} {{ $inst->detail?->last_name }}</div>
            @endforeach
        </div>
    </div>
    @endif

    @if($isInstructor)
    <script>
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-toggle-event]');
        if (!btn) return;
        // In stamp mode, the slot click handler takes over; ignore the personal toggle.
        if (document.body.classList.contains('stamp-active')) return;
        var eventId = btn.dataset.toggleEvent;
        toggleEvent(eventId);
    });
    function toggleEvent(eventId) {
        fetch('{{ route("availability.toggle") }}', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
            body: JSON.stringify({event_id: eventId})
        }).then(r => { if (!r.ok) { return r.text().then(t => { alert('Error '+r.status+': '+t); throw t; }); } return r.json(); })
          .then(() => location.reload())
          .catch(e => console.error(e));
    }
    </script>
    @endif

    @if($isBureau)
    <div id="icStampCursor"><span class="ic-avatar"></span></div>
    <div id="icStampBar">
        <span class="d-flex align-items-center gap-2">
            <span class="ic-avatar" id="icStampBarAvatar"></span>
            <span id="icStampBarName" class="small"></span>
        </span>
        <span class="small text-white-50">{{ __('Tap sessions to toggle') }}</span>
        <button type="button" class="btn btn-sm btn-light" id="icStampBarDone">{{ __('Done') }}</button>
    </div>
    <script>
    (function () {
        const toggleForUrl = '{{ route("availability.toggle-for") }}';
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const isTouch = window.matchMedia('(hover: none), (pointer: coarse)').matches;

        let active = null; // {userId, initial, color, name}

        const cursor = document.getElementById('icStampCursor');
        const cursorAvatar = cursor.querySelector('.ic-avatar');
        const bar = document.getElementById('icStampBar');
        const barAvatar = document.getElementById('icStampBarAvatar');
        const barName = document.getElementById('icStampBarName');
        const exitBtn = document.getElementById('icStampExit');

        function enterStamp(chip) {
            active = {
                userId: chip.dataset.stampUser,
                initial: chip.dataset.stampInitial,
                color: chip.dataset.stampColor,
                name: chip.dataset.stampName,
            };
            document.body.classList.add('stamp-active');
            document.querySelectorAll('.ic-stamp-chip').forEach(c => c.classList.toggle('active', c === chip));
            exitBtn.classList.remove('d-none');

            if (isTouch) {
                barAvatar.style.background = active.color;
                barAvatar.textContent = active.initial;
                barName.textContent = active.name;
                bar.classList.add('show');
            } else {
                cursorAvatar.style.background = active.color;
                cursorAvatar.textContent = active.initial;
                cursor.style.display = 'block';
            }
        }

        function exitStamp() {
            active = null;
            document.body.classList.remove('stamp-active');
            document.querySelectorAll('.ic-stamp-chip').forEach(c => c.classList.remove('active'));
            exitBtn.classList.add('d-none');
            cursor.style.display = 'none';
            bar.classList.remove('show');
        }

        // Chip selection (toggle on/off)
        document.querySelectorAll('.ic-stamp-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                if (active && active.userId === chip.dataset.stampUser) { exitStamp(); }
                else { enterStamp(chip); }
            });
        });
        exitBtn.addEventListener('click', exitStamp);
        document.getElementById('icStampBarDone').addEventListener('click', exitStamp);
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && active) exitStamp(); });

        // Move the floating cursor badge (desktop)
        if (!isTouch) {
            document.addEventListener('mousemove', e => {
                if (!active) return;
                cursor.style.left = e.clientX + 'px';
                cursor.style.top = e.clientY + 'px';
            });
        }

        // Stamp a session slot
        document.addEventListener('click', e => {
            if (!active) return;
            const slot = e.target.closest('.ic-slot');
            if (!slot) return;
            // Don't navigate to the event when stamping.
            const link = e.target.closest('a');
            if (link) e.preventDefault();
            e.stopPropagation();
            stampSlot(slot);
        });

        function stampSlot(slot) {
            if (slot.classList.contains('ic-stamp-saving')) return;
            const eventId = slot.dataset.eventId;
            slot.classList.add('ic-stamp-saving');
            fetch(toggleForUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
                body: JSON.stringify({event_id: eventId, user_id: active.userId}),
            })
            .then(r => { if (!r.ok) return r.text().then(t => { throw new Error('HTTP ' + r.status + ': ' + t); }); return r.json(); })
            .then(data => { applyResult(slot, data); })
            .catch(err => { console.error(err); showToastSafe(err.message || 'Error'); })
            .finally(() => slot.classList.remove('ic-stamp-saving'));
        }

        // Optimistically add/remove the instructor avatar in the slot.
        function applyResult(slot, data) {
            const container = slot.querySelector('.ic-avatars');
            if (!container) return;
            const existing = container.querySelector('.ic-avatar[data-user-id="' + data.user_id + '"]');
            if (data.status === 'removed') {
                if (existing) existing.remove();
            } else if (data.status === 'added') {
                if (!existing) {
                    const a = document.createElement('span');
                    a.className = 'ic-avatar';
                    a.dataset.userId = data.user_id;
                    a.style.background = data.color;
                    a.title = data.name;
                    a.textContent = data.initial;
                    container.appendChild(a);
                    container.appendChild(document.createTextNode(' '));
                }
            }
        }

        function showToastSafe(msg) {
            if (typeof window.showToast === 'function') { window.showToast(msg, 'error'); }
            else { alert(msg); }
        }
    })();
    </script>
    @endif
</x-layout>
