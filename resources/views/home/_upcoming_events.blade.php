<div class="card dc-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>@icon('📅') {{ __('Upcoming Events') }}</span>
        <a href="{{ route('events.index') }}" class="btn btn-sm btn-outline-primary py-0">{{ __('All') }}</a>
    </div>
    <div class="list-group list-group-flush">
        @forelse($widget['data']['events'] ?? [] as $event)
            <a href="{{ route('events.show', $event) }}" class="list-group-item list-group-item-action px-3 py-2">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-semibold" style="font-size:.95rem">
                            @if($event->color_hex)
                                <span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:{{ $event->color_hex }}"></span>
                            @endif
                            {{ $event->title }}
                        </div>
                        <small class="text-muted">
                            @if($event->location){{ $event->location }}@endif
                            @if($event->event_time) · {{ \Illuminate\Support\Str::substr($event->event_time, 0, 5) }}@endif
                            @if($event->max_participants)
                                · {{ $event->registrations_count ?? $event->registrations()->count() }}/{{ $event->max_participants }}
                            @endif
                        </small>
                    </div>
                    <div class="text-end ms-2" style="min-width:50px">
                        <div class="fw-bold text-primary" style="font-size:.95rem">{{ $event->event_date->format('d') }}</div>
                        <small class="text-muted text-uppercase">{{ $event->event_date->translatedFormat('M') }}</small>
                    </div>
                </div>
            </a>
        @empty
            <div class="list-group-item text-muted small">{{ __('No upcoming events.') }}</div>
        @endforelse
    </div>
</div>
