@props(['id' => null])
<div class="dropdown d-inline">
    <button class="btn btn-sm btn-outline-secondary py-0 px-1" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('Actions') }}">⋯</button>
    <ul class="dropdown-menu dropdown-menu-end" style="font-size:.85rem">
        {{ $slot }}
    </ul>
</div>
