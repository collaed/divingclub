<x-layout :title="__('Seasons')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">{{ __('Season Management') }}</h4>
        <a href="{{ route('admin.seasons.create') }}" class="btn btn-primary btn-sm">{{ __('New Season') }}</a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>{{ __('Year') }}</th><th>{{ __('Name') }}</th><th>{{ __('Period') }}</th><th>{{ __('Events') }}</th><th>{{ __('Active') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($seasons as $s)
                <tr>
                    <td>{{ $s->year }}</td>
                    <td><a href="{{ route('admin.seasons.show', $s) }}">{{ $s->name }}</a></td>
                    <td>{{ $s->start_date->format('d/m/Y') }} — {{ $s->end_date->format('d/m/Y') }}</td>
                    <td>{{ $s->events_count }}</td>
                    <td>
                        @if($s->is_active) <span class="badge bg-success">{{ __('Active') }}</span>
                        @else
                            <form method="POST" action="{{ route('admin.seasons.activate', $s) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-success">{{ __('Activate') }}</button>
                            </form>
                        @endif
                    </td>
                    <td><a href="{{ route('admin.seasons.show', $s) }}" class="btn btn-sm btn-outline-primary">{{ __('Manage') }}</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layout>
