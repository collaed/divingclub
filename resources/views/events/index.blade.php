<x-layout :title="__('Calendar')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">{{ __('Event Calendar') }}</h4>
        <div class="d-flex gap-2">
            <div class="btn-group btn-group-sm">
                <a href="{{ route('events.index', ['view' => 'month', 'date' => $date->format('Y-m-d')]) }}" class="btn btn-{{ $view === 'month' ? 'primary' : 'outline-primary' }}">{{ __('Month') }}</a>
                <a href="{{ route('events.index', ['view' => 'week', 'date' => $date->format('Y-m-d')]) }}" class="btn btn-{{ $view === 'week' ? 'primary' : 'outline-primary' }}">{{ __('Week') }}</a>
                <a href="{{ route('events.index', ['view' => 'day', 'date' => $date->format('Y-m-d')]) }}" class="btn btn-{{ $view === 'day' ? 'primary' : 'outline-primary' }}">{{ __('Day') }}</a>
            </div>
            @if(auth()->check() && auth()->user()->isBureau())
                <a href="{{ route('events.create') }}" class="btn btn-sm btn-primary">{{ __('New Event') }}</a>
            @endif
            <a href="{{ route('calendar.ics') }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Subscribe in Google Calendar, Apple Calendar, Outlook...') }}">📅 iCal</a>
        </div>
    </div>

    {{-- Navigation --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        @php
            $prev = $view === 'month' ? $date->copy()->subMonth() : ($view === 'week' ? $date->copy()->subWeek() : $date->copy()->subDay());
            $next = $view === 'month' ? $date->copy()->addMonth() : ($view === 'week' ? $date->copy()->addWeek() : $date->copy()->addDay());
        @endphp
        <a href="{{ route('events.index', ['view' => $view, 'date' => $prev->format('Y-m-d')]) }}" class="btn btn-outline-secondary btn-sm">← {{ __('Previous') }}</a>
        <h5 class="mb-0">
            @if($view === 'month') {{ $date->format('F Y') }}
            @elseif($view === 'week') {{ $start->format('d M') }} — {{ $end->format('d M Y') }}
            @else {{ $date->format('l, d F Y') }}
            @endif
        </h5>
        <a href="{{ route('events.index', ['view' => $view, 'date' => $next->format('Y-m-d')]) }}" class="btn btn-outline-secondary btn-sm">{{ __('Next') }} →</a>
    </div>

    {{-- Event type legend --}}
    <div class="mb-3 d-flex gap-3 flex-wrap small">
        @foreach(['pool' => '#0077be', 'dive' => '#003366', 'training' => '#28a745', 'theory' => '#6f42c1', 'social' => '#ffc107'] as $type => $color)
            <span><span class="badge" style="background:{{ $color }}">⠀</span> {{ ucfirst($type) }}</span>
        @endforeach
    </div>

    @if($view === 'month')
        {{-- Month grid --}}
        @php
            $monthStart = $date->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::MONDAY);
            $monthEnd = $date->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY);
            $day = $monthStart->copy();
            $eventsByDate = $events->groupBy(fn($e) => $e->event_date->format('Y-m-d'));
        @endphp
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr>
                    @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d)
                        <th class="text-center small" style="width:14.28%">{{ __($d) }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                @while($day->lte($monthEnd))
                    <tr>
                    @for($i = 0; $i < 7; $i++)
                        @php $isCurrentMonth = $day->month === $date->month; $dayEvents = $eventsByDate[$day->format('Y-m-d')] ?? collect(); @endphp
                        <td class="p-1 {{ $isCurrentMonth ? '' : 'bg-light' }}" style="vertical-align:top; min-height:80px;">
                            <div class="small {{ $day->isToday() ? 'fw-bold text-primary' : 'text-muted' }}">{{ $day->day }}</div>
                            @foreach($dayEvents->take(3) as $ev)
                                <a href="{{ route('events.show', $ev) }}" class="d-block text-decoration-none small text-truncate rounded px-1 mb-1 text-white" style="background:{{ $ev->typeColor() }}; font-size:0.7rem;">
                                    {{ $ev->event_time ? substr($ev->event_time, 0, 5) : '' }} {{ Str::limit($ev->title, 15) }}
                                </a>
                            @endforeach
                            @if($dayEvents->count() > 3)
                                <span class="small text-muted">+{{ $dayEvents->count() - 3 }}</span>
                            @endif
                        </td>
                        @php $day->addDay(); @endphp
                    @endfor
                    </tr>
                @endwhile
                </tbody>
            </table>
        </div>
    @else
        {{-- List view for week/day --}}
        @forelse($events as $event)
            <div class="card dc-card mb-2">
                <div class="card-body py-2 d-flex align-items-center">
                    <span class="badge me-3" style="background:{{ $event->typeColor() }}">{{ ucfirst($event->event_type) }}</span>
                    <div class="flex-grow-1">
                        <a href="{{ route('events.show', $event) }}" class="text-decoration-none fw-bold">{{ $event->title }}</a>
                        <div class="small text-muted">
                            {{ $event->event_date->format('D d/m/Y') }}
                            {{ $event->event_time ? '@ ' . substr($event->event_time, 0, 5) : '' }}
                            {{ $event->end_time ? '— ' . substr($event->end_time, 0, 5) : '' }}
                            @if($event->location) · {{ $event->location }} @endif
                        </div>
                    </div>
                    <div class="text-end small">
                        <span class="badge bg-secondary">{{ $event->confirmed_count }}{{ $event->max_participants ? '/' . $event->max_participants : '' }}</span>
                        @if($event->waiting_count > 0)
                            <span class="badge bg-warning text-dark">+{{ $event->waiting_count }} {{ __('waiting') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="text-muted text-center py-4">{{ __('No events for this period.') }}</p>
        @endforelse
    @endif
</x-layout>
