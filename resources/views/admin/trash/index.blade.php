<x-admin-layout :title="__('Recycle Bin')">
    <h4 class="mb-1">@icon('🗑️') {{ __('Recycle Bin') }}</h4>
    <p class="text-muted small">{{ __('Soft-deleted records and cancelled events. Restore, or (bureau master) remove for good.') }}</p>

    <ul class="nav nav-pills mb-3 flex-wrap gap-1">
        @foreach($kinds as $k => $s)
            <li class="nav-item">
                <a class="nav-link {{ $kind === $k ? 'active' : '' }}" href="{{ route('admin.trash.index', ['kind' => $k]) }}">
                    @icon($s['icon']) {{ __($s['label']) }}
                    @if(($counts[$k] ?? 0) > 0)<span class="badge bg-secondary ms-1">{{ $counts[$k] }}</span>@endif
                </a>
            </li>
        @endforeach
        <li class="nav-item">
            <a class="nav-link {{ $cancelledMode ? 'active' : '' }}" href="{{ route('admin.trash.index', ['kind' => 'cancelled-events']) }}">
                @icon('🚫') {{ __('Cancelled events') }}
                @if(($counts['cancelled-events'] ?? 0) > 0)<span class="badge bg-secondary ms-1">{{ $counts['cancelled-events'] }}</span>@endif
            </a>
        </li>
    </ul>

    <x-table class="table-hover">
        <thead>
            <tr>
                <th>{{ __($spec['label']) }}</th>
                <th>{{ $cancelledMode ? __('Event date') : __('Deleted') }}</th>
                @unless($cancelledMode)<th>{{ __('By') }}</th>@endunless
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @if($cancelledMode)
                        <td><a href="{{ route('events.show', $row) }}" class="text-decoration-none">{{ $row->title ?: '#'.$row->getKey() }}</a></td>
                        <td class="small text-muted">{{ $row->event_date?->format('d/m/Y') }}</td>
                        <td class="text-end text-nowrap">
                            <form method="POST" action="{{ route('admin.trash.cancelled-event.restore', $row) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-success py-0 px-2">↩ {{ __('Restore') }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.trash.cancelled-event.trash', $row) }}" class="d-inline"
                                  data-confirm="{{ __('Move “:name” to the recycle bin?', ['name' => $row->title]) }}" data-confirm-style="danger" data-confirm-btn="{{ __('Move to bin') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger py-0 px-2">🗑 {{ __('Bin it') }}</button>
                            </form>
                        </td>
                    @else
                        @php $actor = $actors->get($row->id)?->user; @endphp
                        <td>{{ $row->{$spec['name']} ?: '#'.$row->getKey() }}</td>
                        <td class="small text-muted">{{ $row->deleted_at?->format('d/m/Y H:i') }}</td>
                        <td class="small text-muted">{{ $actor?->name ?? '—' }}</td>
                        <td class="text-end text-nowrap">
                            <form method="POST" action="{{ route('admin.trash.restore', [$kind, $row->getKey()]) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-success py-0 px-2">↩ {{ __('Restore') }}</button>
                            </form>
                            @if(auth()->user()?->hasRole('bureau_master'))
                                <form method="POST" action="{{ route('admin.trash.force-delete', [$kind, $row->getKey()]) }}" class="d-inline"
                                      data-confirm="{{ __('Delete this permanently? This cannot be undone.') }}" data-confirm-style="danger" data-confirm-btn="{{ __('Delete for good') }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger py-0 px-2">✕</button>
                                </form>
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-4">{{ __('Nothing here.') }}</td></tr>
            @endforelse
        </tbody>
    </x-table>

    {{ $rows->links() }}
</x-admin-layout>
