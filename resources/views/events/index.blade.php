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
            <a href="{{ route('calendar.ics') }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Subscribe in Google Calendar, Apple Calendar, Outlook...') }}">@icon('📅') iCal</a>
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
    <div class="mb-2 d-flex gap-3 flex-wrap small">
        @foreach(['pool' => '#0077be', 'dive' => '#003366', 'training' => '#28a745', 'theory' => '#6f42c1', 'social' => '#ffc107', 'trip' => '#6a1b9a'] as $type => $color)
            <span><span class="badge" style="background:{{ $color }}">⠀</span> {{ ucfirst($type) }}</span>
        @endforeach
    </div>

    {{-- Equipment-priority focus legend --}}
    <div class="mb-3 d-flex gap-3 flex-wrap small">
        <span class="text-muted">{{ __('Priority equipment access:') }}</span>
        @foreach(config('event_focus_groups') as $slug => $fg)
            <span><span class="badge focus-{{ $slug }}" style="background-color:#5c6bc0">⠀</span> {{ $fg['icon'] }} {{ $fg['label'] }}</span>
        @endforeach
    </div>

    @if($view === 'month')
        {{-- Month grid --}}
        @php
            $monthStart = $date->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::MONDAY);
            $monthEnd = $date->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY);
            $day = $monthStart->copy();
            $eventsByDate = collect();
            foreach ($events as $e) {
                $eStart = $e->event_date->copy();
                $eEnd = ($e->end_date && $e->end_date->gt($e->event_date)) ? $e->end_date->copy() : $eStart->copy();
                for ($d = $eStart->copy(); $d->lte($eEnd); $d->addDay()) {
                    $key = $d->format('Y-m-d');
                    $eventsByDate[$key] = ($eventsByDate[$key] ?? collect())->push($e);
                }
            }
        @endphp
        <div class="table-responsive">
            <table class="table table-bordered calendar-month">
                <thead><tr>
                    @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d)
                        <th class="text-center small" style="width:14.28%">{{ __($d) }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                @while($day->lte($monthEnd))
                    <tr>
                    @for($i = 0; $i < 7; $i++)
                        @php
                            $isCurrentMonth = $day->month === $date->month;
                            $isToday = $isCurrentMonth && $day->isToday();
                            $dayEvents = $eventsByDate[$day->format('Y-m-d')] ?? collect();
                            $weekdayLabel = __(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'][$day->isoWeekday() - 1]);
                        @endphp
                        <td class="{{ $isCurrentMonth ? '' : 'bg-light' }} {{ $isToday ? 'cal-today' : '' }}" data-weekday="{{ $weekdayLabel }}">
                            @if($isCurrentMonth)
                                <div class="cal-daynum small {{ $isToday ? 'fw-bold text-primary' : 'text-muted' }}">{{ $day->day }}</div>
                            @else
                                <a href="{{ route('events.index', ['view' => 'month', 'date' => $day->format('Y-m-d')]) }}" class="small text-muted text-decoration-none d-block" title="{{ __('Go to :month', ['month' => $day->format('F Y')]) }}">{{ $day->day }}</a>
                            @endif
                            @foreach($dayEvents->take(3) as $ev)
                                @php $focusGroup = $ev->focus_group ? config("event_focus_groups.{$ev->focus_group}") : null; @endphp
                                <div class="d-flex align-items-center gap-1 mb-1">
                                    <a href="{{ route('events.show', $ev) }}" class="flex-grow-1 text-decoration-none small text-truncate rounded px-1 text-white {{ $focusGroup ? 'focus-'.$ev->focus_group : '' }} {{ $ev->status === 'cancelled' ? 'text-decoration-line-through' : '' }}" style="min-width:0; background-color:{{ $ev->typeColor() }}; font-size:0.7rem; {{ $ev->status === 'cancelled' ? 'opacity:0.5;' : '' }}" title="{{ $focusGroup['label'] ?? '' }}">
                                        @if($focusGroup) <span aria-hidden="true">{{ $focusGroup['icon'] }}</span> @endif
                                        {{ $ev->event_time ? substr($ev->event_time, 0, 5) : '' }} {{ Str::limit($ev->title, 15) }}
                                    </a>
                                    @if($ev->status === 'cancelled')
                                        <span class="badge bg-danger flex-shrink-0" style="font-size:0.6rem;">{{ __('Cancelled') }}</span>
                                    @endif
                                </div>
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
            <div class="card dc-card mb-2 {{ $event->status === 'cancelled' ? 'bg-light' : '' }}">
                <div class="card-body py-2 d-flex align-items-center">
                    <span class="badge me-3" style="background:{{ $event->typeColor() }}">{{ ucfirst($event->event_type) }}</span>
                    <div class="flex-grow-1">
                        <a href="{{ route('events.show', $event) }}" class="text-decoration-none fw-bold {{ $event->status === 'cancelled' ? 'text-decoration-line-through text-muted' : '' }}">{{ $event->title }}</a>
                        @if($event->status === 'cancelled')
                            <span class="badge bg-danger ms-1">{{ __('Cancelled') }}</span>
                            @if(auth()->user()?->isBureau())
                                <form method="POST" action="{{ route('events.uncancel', $event) }}" class="d-inline ms-1">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success py-0 px-2">↩ {{ __('Restore') }}</button>
                                </form>
                            @endif
                        @endif
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

    @if(auth()->user()?->isBureau() || auth()->user()?->hasAnyRole(['instructor', 'instructor_apnea']))
        <div class="card dc-card mt-4">
            <div class="card-body">
                <h6>{{ __('Recent event changes') }}</h6>
                @if($recentEventChanges->isEmpty())
                    <p class="text-muted small mb-0">{{ __('No recent changes.') }}</p>
                @else
                    <ul class="list-unstyled small mb-0">
                        @foreach($recentEventChanges as $log)
                            @php
                                $changedEvent = \App\Models\Event::withTrashed()->find($log->model_id);
                                $fields = $log->action === 'updated' && $log->new_values
                                    ? implode(', ', array_diff(array_keys($log->new_values), ['updated_at']))
                                    : null;
                            @endphp
                            <li class="mb-1 d-flex align-items-start gap-2">
                                <span class="badge bg-{{ $log->action === 'deleted' ? 'danger' : ($log->action === 'created' ? 'success' : 'info') }}">{{ __($log->action) }}</span>
                                <span class="flex-grow-1">
                                    @if($changedEvent)
                                        <a href="{{ route('events.show', $changedEvent) }}">{{ $changedEvent->title }}</a>
                                    @else
                                        {{ __('Event #:id (deleted)', ['id' => $log->model_id]) }}
                                    @endif
                                    — {{ $log->user?->name ?? __('Unknown') }}
                                    <span class="text-muted">({{ $log->created_at->diffForHumans() }})</span>
                                    @if($fields)
                                        <div class="text-muted">{{ __('Changed') }}: {{ $fields }}</div>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif
</x-layout>
