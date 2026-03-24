<x-layout :title="__('Instructor Availability')">
    @php
        $actColors = $colors;
        $dow = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">@icon('📅') {{ __('Instructor Planning') }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('availability.index', ['month' => $start->copy()->subMonth()->format('Y-m')]) }}" class="btn btn-sm btn-outline-secondary">←</a>
            <span class="btn btn-sm btn-primary disabled">{{ $start->translatedFormat('F Y') }}</span>
            <a href="{{ route('availability.index', ['month' => $start->copy()->addMonth()->format('Y-m')]) }}" class="btn btn-sm btn-outline-secondary">→</a>
        </div>
    </div>

    @if($isInstructor)
        <div class="alert alert-info small py-2 mb-3">
            @icon('💡') {{ __('Click an event to mark yourself available. Your initial will appear. Click again to remove.') }}
        </div>
    @endif

    <div class="table-responsive">
        <table class="table table-bordered table-sm text-center align-middle" style="font-size:.85rem">
            <thead class="table-dark">
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
                            <td class="{{ !$inMonth ? 'text-muted bg-light' : '' }} {{ $isWeekend && $inMonth ? 'bg-light' : '' }} {{ $day->isToday() ? 'border-primary border-2' : '' }}" style="vertical-align:top;min-width:100px;height:60px">
                                @if($inMonth)
                                    <div class="fw-bold small {{ $isPast ? 'text-muted' : '' }}">{{ $day->format('d') }}</div>
                                    @foreach($dayEvents as $ev)
                                        @php
                                            $evAvails = $availByEvent->get($ev->id, collect());
                                            $myAvail = $evAvails->firstWhere('user_id', auth()->id());
                                        @endphp
                                        <div class="d-block mb-1 rounded px-1 text-start" style="background:{{ $ev->color_hex ?? '#6c757d' }};font-size:.65rem;color:#fff">
                                            <div class="d-flex align-items-center gap-1">
                                                <a href="{{ route('events.show', $ev) }}" class="text-white text-truncate text-decoration-none flex-grow-1" style="max-width:70px" title="{{ $ev->title }}{{ $ev->event_time ? ' · '.Str::substr($ev->event_time, 0, 5) : '' }}">{{ Str::limit($ev->title, 12) }}</a>
                                                @if($isInstructor && !$isPast)
                                                    <span class="ms-auto" style="cursor:pointer;font-size:.6rem" onclick="toggleEvent({{ $ev->id }})" title="{{ $myAvail ? __('Remove availability') : __('Mark available') }}">{{ $myAvail ? '✅' : '➕' }}</span>
                                                @endif
                                            </div>
                                            @if($evAvails->isNotEmpty())
                                                <span class="d-block" style="font-size:.6rem;letter-spacing:1px">@foreach($evAvails as $av)@php
                                                    $ini = $av->user->detail?->instructor_initial ?: mb_strtoupper(mb_substr($av->user->detail?->first_name ?? '?', 0, 1));
                                                    $ic = $av->user->detail?->instructor_color ?? '#6c757d';
                                                @endphp<span class="badge fw-bold px-1" style="background:{{ $ic }};color:#fff;font-size:.55rem" title="{{ $av->user->detail?->first_name }} {{ $av->user->detail?->last_name }}">{{ $ini }}</span> @endforeach</span>
                                            @endif
                                        </div>
                                    @endforeach
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

    {{-- Instructor initials legend --}}
    <div class="mt-3">
        <strong class="small text-muted">{{ __('Instructors') }}:</strong>
        <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-1 mt-1">
            @foreach($instructors as $inst)
                @php
                    $ini = $inst->detail?->instructor_initial ?: mb_strtoupper(mb_substr($inst->detail?->first_name ?? '?', 0, 1));
                    $ic = $inst->detail?->instructor_color ?? '#6c757d';
                @endphp
                <div class="col small"><span class="badge me-1" style="background:{{ $ic }};color:#fff">{{ $ini }}</span>{{ $inst->detail?->first_name }} {{ $inst->detail?->last_name }}</div>
            @endforeach
        </div>
    </div>

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
