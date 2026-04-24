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
                                            <div class="flex-fill rounded px-1 text-start" style="background:{{ $actColor }};font-size:.6rem;color:{{ $actText }};min-width:0">
                                                <div class="d-flex align-items-center gap-1">
                                                    <a href="{{ route('events.show', $ev) }}" class="text-truncate text-decoration-none flex-grow-1" style="color:{{ $actText }};max-width:50px" title="{{ $ev->title }}{{ $ev->event_time ? ' · '.Str::substr($ev->event_time, 0, 5) : '' }}">{{ Str::limit($ev->title, 8) }}</a>
                                                    @if($isInstructor && !$isPast)
                                                        <span class="ms-auto ic-toggle {{ $myAvail ? 'ic-toggle-remove' : 'ic-toggle-add' }}" onclick="toggleEvent({{ $ev->id }})" title="{{ $myAvail ? __('Remove availability') : __('Mark available') }}">{{ $myAvail ? '✗' : '✓' }}</span>
                                                    @endif
                                                </div>
                                                @if($evAvails->isNotEmpty())
                                                    <span class="d-block" style="font-size:.55rem;letter-spacing:1px">@foreach($evAvails as $av)@php
                                                        $ini = $av->user->detail?->instructor_initial ?: mb_strtoupper(mb_substr($av->user->detail?->first_name ?? '?', 0, 1));
                                                        $ic = $av->user->detail?->instructor_color ?? '#00695c';
                                                    @endphp<span class="ic-avatar" style="background:{{ $ic }}" title="{{ $av->user->detail?->first_name }} {{ $av->user->detail?->last_name }}">{{ $ini }}</span> @endforeach</span>
                                                @endif
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
                                            <div class="d-block mb-1 rounded px-1 text-start" style="background:{{ $actColor }};font-size:.65rem;color:{{ $actText }}">
                                                <div class="d-flex align-items-center gap-1">
                                                    <a href="{{ route('events.show', $ev) }}" class="text-truncate text-decoration-none flex-grow-1" style="color:{{ $actText }};max-width:70px" title="{{ $ev->title }}{{ $ev->event_time ? ' · '.Str::substr($ev->event_time, 0, 5) : '' }}">{{ Str::limit($ev->title, 12) }}</a>
                                                    @if($isInstructor && !$isPast)
                                                        <span class="ms-auto ic-toggle {{ $myAvail ? 'ic-toggle-remove' : 'ic-toggle-add' }}" onclick="toggleEvent({{ $ev->id }})" title="{{ $myAvail ? __('Remove availability') : __('Mark available') }}">{{ $myAvail ? '✗' : '✓' }}</span>
                                                    @endif
                                                </div>
                                                @if($evAvails->isNotEmpty())
                                                    <span class="d-block" style="font-size:.6rem;letter-spacing:1px">@foreach($evAvails as $av)@php
                                                        $ini = $av->user->detail?->instructor_initial ?: mb_strtoupper(mb_substr($av->user->detail?->first_name ?? '?', 0, 1));
                                                        $ic = $av->user->detail?->instructor_color ?? '#00695c';
                                                    @endphp<span class="ic-avatar" style="background:{{ $ic }}" title="{{ $av->user->detail?->first_name }} {{ $av->user->detail?->last_name }}">{{ $ini }}</span> @endforeach</span>
                                                @endif
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
</x-layout>
