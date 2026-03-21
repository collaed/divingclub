@props(['column', 'label'])
@php
    $current = request('sort');
    $dir = request('dir', 'asc');
    $isActive = $current === $column;
    $nextDir = $isActive && $dir === 'asc' ? 'desc' : 'asc';
    $arrow = $isActive ? ($dir === 'asc' ? '▲' : '▼') : '';
@endphp
<a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'dir' => $nextDir, 'page' => 1]) }}"
   class="text-decoration-none text-nowrap {{ $isActive ? 'fw-bold' : 'text-muted' }}">
    {{ $label }} <small>{{ $arrow }}</small>
</a>
