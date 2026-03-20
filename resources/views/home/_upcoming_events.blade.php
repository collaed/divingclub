<div class="card dc-card mb-4">
    <div class="card-header">📅 {{ __('Upcoming Events') }}</div>
    <div class="list-group list-group-flush">
        @forelse($widget['data']['events'] ?? [] as $event)
            <a href="{{ route('events.show', $event) }}" class="list-group-item list-group-item-action py-2">
                <div class="d-flex justify-content-between">
                    <span>{{ $event->title }}</span>
                    <small class="text-muted">{{ $event->event_date->format('d/m') }}</small>
                </div>
            </a>
        @empty
            <div class="list-group-item text-muted">{{ __('No upcoming events.') }}</div>
        @endforelse
    </div>
</div>
