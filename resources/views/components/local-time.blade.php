@props(['at', 'dateOnly' => false])
@php
    $carbon = $at instanceof \Carbon\Carbon ? $at : \Carbon\Carbon::parse($at);
@endphp
@if($at)
    {{--
        Server-rendered fallback is the app timezone (UTC) — a tiny global
        script in the layout upgrades this to the viewer's own browser
        locale/timezone on load. See resources/views/components/layout.blade.php.
    --}}
    <span data-local-datetime="{{ $carbon->toIso8601String() }}" data-local-date-only="{{ $dateOnly ? '1' : '0' }}">{{ $dateOnly ? $carbon->format('Y-m-d') : $carbon->format('Y-m-d H:i') }}</span>
@endif
