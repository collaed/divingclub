@props(['action', 'resetUrl' => null])
<form method="GET" action="{{ $action }}" class="row g-2 mb-3 align-items-end">
    {{ $slot }}
    <div class="col-auto">
        <button class="btn btn-sm btn-outline-primary">{{ __('Filter') }}</button>
        @if($resetUrl && request()->hasAny(array_keys(request()->except('page'))))
            <a href="{{ $resetUrl }}" class="btn btn-sm btn-outline-secondary">✕</a>
        @endif
    </div>
</form>
