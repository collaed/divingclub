@props(['items' => []])
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="/">{{ __('Home') }}</a></li>
        @foreach($items as $label => $url)
            @if($loop->last)
                <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
            @else
                <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
            @endif
        @endforeach
    </ol>
</nav>
