@foreach($navItems as $dropdown)
    @php($visibleChildren = $dropdown->children->filter(fn ($child) => $child->isVisibleTo(auth()->user())))
    @continue(! $dropdown->isVisibleTo(auth()->user()) || $visibleChildren->isEmpty())
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle {{ $dropdown->isCurrent() ? 'active fw-bold' : '' }}" href="#" data-bs-toggle="dropdown">{{ $dropdown->resolvedLabel() }}</a>
        <ul class="dropdown-menu">
            @foreach($visibleChildren as $child)
                @if($child->type === 'divider')
                    <li><hr class="dropdown-divider"></li>
                @else
                    <li><a class="dropdown-item" href="{{ $child->resolvedUrl() }}">@icon($child->icon ?? '') {{ $child->resolvedLabel() }}</a></li>
                @endif
            @endforeach
        </ul>
    </li>
@endforeach
