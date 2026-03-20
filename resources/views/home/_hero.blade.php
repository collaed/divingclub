@if(($widget['data']['photos'] ?? collect())->count())
<x-slideshow :photos="$widget['data']['photos']" :height="$widget['config']['height'] ?? '350px'" :interval="7000" :rounded="true">
    <div class="text-center px-4">
        <h2 class="fw-bold mb-2">{{ $widget['config']['title'] ?? '' }}</h2>
        <p class="mb-0 fs-5">{{ $widget['config']['subtitle'] ?? '' }}</p>
    </div>
</x-slideshow>
<div class="mb-4"></div>
@endif
