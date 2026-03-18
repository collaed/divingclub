<x-layout :title="__('Schedule Preview')">
    <h4 class="mb-4">{{ __('Schedule Preview') }} — {{ $season->name }}</h4>
    <p class="text-muted">{{ __('Review the dates below before generating events. Skipped dates are shown in red.') }}</p>

    @php $generated = collect($preview)->where('skip', false)->count(); $skipped = collect($preview)->where('skip', true)->count(); @endphp
    <div class="mb-3">
        <span class="badge bg-success">{{ $generated }} {{ __('events to create') }}</span>
        <span class="badge bg-danger">{{ $skipped }} {{ __('dates skipped') }}</span>
    </div>

    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Day') }}</th><th>{{ __('Pattern') }}</th><th>{{ __('Time') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @foreach($preview as $entry)
                <tr class="{{ $entry['skip'] ? 'table-danger' : '' }}">
                    <td>{{ $entry['date']->format('d/m/Y') }}</td>
                    <td>{{ $entry['date']->format('l') }}</td>
                    <td>
                        <span class="badge" style="background:{{ $entry['pattern']->color_hex ?? '#6c757d' }}">{{ ucfirst($entry['pattern']->event_type) }}</span>
                        {{ $entry['pattern']->title }}
                    </td>
                    <td>{{ $entry['pattern']->start_time }}{{ $entry['pattern']->end_time ? '—'.$entry['pattern']->end_time : '' }}</td>
                    <td>
                        @if($entry['skip'])
                            <span class="text-danger">⛔ {{ $entry['skip_reason'] }}</span>
                        @else
                            <span class="text-success">✓ {{ __('Will create') }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        <form method="POST" action="{{ route('admin.seasons.generate', $season) }}" class="d-inline">
            @csrf
            <button class="btn btn-primary">{{ __('Confirm & Generate :count Events', ['count' => $generated]) }}</button>
        </form>
        <a href="{{ route('admin.seasons.show', $season) }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
    </div>
</x-layout>
