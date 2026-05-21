@props(['value', 'label', 'icon' => null, 'color' => null])
<div class="card dc-card dc-stat-card" @if($color) style="border-left-color: {{ $color }}" @endif>
    <div class="card-body text-center py-3">
        @if($icon)<div class="mb-1" style="font-size:1.5rem">{{ $icon }}</div>@endif
        <div class="dc-stat-value">{{ $value }}</div>
        <div class="dc-stat-label">{{ $label }}</div>
    </div>
</div>
