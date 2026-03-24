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
                                        <div class="d-block mb-1 rounded px-1 text-start {{ $isInstructor && !$isPast ? 'event-toggle' : '' }}"
                                             style="background:{{ $ev->color_hex ?? '#6c757d' }};font-size:.65rem;color:#fff;{{ $isInstructor && !$isPast ? 'cursor:pointer' : '' }}{{ $myAvail ? ';outline:2px solid #000' : '' }}"
                                             title="{{ $ev->title }}{{ $ev->event_time ? ' · '.Str::substr($ev->event_time, 0, 5) : '' }}"
                                             @if($isInstructor && !$isPast) onclick="toggleEvent({{ $ev->id }})" @endif>
                                            <span class="text-truncate d-block" style="max-width:80px">{{ Str::limit($ev->title, 14) }}</span>
                                            @if($evAvails->isNotEmpty())
                                                <span class="d-block" style="font-size:.6rem;letter-spacing:1px">@foreach($evAvails as $av)@php
                                                    $ini = $av->user->detail?->instructor_initial ?: mb_strtoupper(mb_substr($av->user->detail?->first_name ?? '?', 0, 1));
                                                    $ic = $av->user->detail?->instructor_color;
                                                @endphp<span class="fw-bold" style="{{ $ic ? 'color:'.$ic : '' }}" title="{{ $av->user->detail?->first_name }} {{ $av->user->detail?->last_name }}">{{ $ini }}</span> @endforeach</span>
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
    <div class="mt-2 small text-muted">
        <strong>{{ __('Instructors') }}:</strong>
        @foreach($instructors as $inst)
            @php $ini = $inst->detail?->instructor_initial ?: mb_strtoupper(mb_substr($inst->detail?->first_name ?? '?', 0, 1)); @endphp
            @php $ic = $inst->detail?->instructor_color; @endphp
            <span class="badge me-1" style="background:{{ $ic ?? '#6c757d' }};color:#fff">{{ $ini }}</span>{{ $inst->detail?->first_name }}
        @endforeach
    </div>

    @if($isInstructor)
    <script>
    function toggleEvent(eventId) {
        fetch('{{ route("availability.toggle") }}', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
            body: JSON.stringify({event_id: eventId})
        }).then(r => r.json()).then(() => location.reload());
    }
    </script>
    @endif
</x-layout>
