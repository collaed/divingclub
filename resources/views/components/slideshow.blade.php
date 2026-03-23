{{-- Slideshow component with Ken Burns pan/zoom effect | ClubCEP.eu
     Usage: <x-slideshow :photos="$photos" height="400px" />
     Photos: collection/array of objects with ->url or ['url'] (full URL to image)
     Optional: interval (ms), height (CSS), rounded (bool), overlay (slot) --}}

@props([
    'photos' => [],
    'interval' => 6000,
    'height' => '300px',
    'rounded' => true,
    'id' => 'ss-' . uniqid(),
])

@php
    $items = collect($photos)->values();
@endphp

@if($items->count())
<div class="dc-slideshow {{ $rounded ? 'rounded' : '' }}" id="{{ $id }}"
     style="height:{{ $height }};--ss-interval:{{ $interval }}ms"
     role="img" aria-label="{{ __('Photo slideshow') }}">

    @foreach($items as $i => $photo)
        @php $url = is_string($photo) ? $photo : ($photo->url ?? $photo['url'] ?? asset('storage/' . ($photo->path ?? $photo['path'] ?? ''))); @endphp
        <div class="dc-ss-slide {{ $i === 0 ? 'active' : '' }}" data-index="{{ $i }}">
            <div class="dc-ss-bg" style="background-image:url('{{ $url }}')"></div>
            <div class="dc-ss-fg" style="background-image:url('{{ $url }}')"></div>
        </div>
    @endforeach

    {{-- Optional overlay slot (e.g. hero text) --}}
    @if($slot->isNotEmpty())
        <div class="dc-ss-overlay">{{ $slot }}</div>
    @endif

    {{-- Dots (only if >1 photo) --}}
    @if($items->count() > 1)
        <div class="dc-ss-dots">
            @foreach($items as $i => $photo)
                <button class="dc-ss-dot {{ $i === 0 ? 'active' : '' }}" data-slide="{{ $i }}" aria-label="Photo {{ $i + 1 }}"></button>
            @endforeach
        </div>
    @endif
</div>

@once
<style>
    .dc-slideshow {
        position: relative; overflow: hidden; width: 100%; background: #1a1a2e;
    }
    .dc-ss-slide {
        position: absolute; inset: 0;
        opacity: 0; transition: opacity 1.2s ease-in-out;
        animation: dc-kenburns var(--ss-interval, 6000ms) ease-in-out infinite alternate;
        transform-origin: center;
    }
    /* Blurred stretched background — fills empty sides */
    .dc-ss-bg {
        position: absolute; inset: -20px;
        background-size: cover; background-position: center;
        filter: blur(20px) brightness(0.7); transform: scale(1.1);
    }
    /* Sharp photo — shown at natural aspect ratio, no crop */
    .dc-ss-fg {
        position: absolute; inset: 0;
        background-size: contain; background-position: center; background-repeat: no-repeat;
    }
    .dc-ss-slide.active { opacity: 1; }
    /* Alternate pan directions per slide */
    .dc-ss-slide:nth-child(odd)  { animation-name: dc-kb-a; }
    .dc-ss-slide:nth-child(even) { animation-name: dc-kb-b; }

    @keyframes dc-kb-a {
        0%   { transform: scale(1)    translate(0, 0); }
        100% { transform: scale(1.08) translate(-1.5%, -1%); }
    }
    @keyframes dc-kb-b {
        0%   { transform: scale(1.05) translate(1%, 0.5%); }
        100% { transform: scale(1)    translate(-0.5%, -1.5%); }
    }

    .dc-ss-overlay {
        position: absolute; inset: 0; z-index: 2;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(transparent 40%, rgba(0,0,0,0.5));
        color: white; text-shadow: 0 2px 8px rgba(0,0,0,0.6);
    }
    .dc-ss-dots {
        position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%);
        z-index: 3; display: flex; gap: 6px;
    }
    .dc-ss-dot {
        width: 8px; height: 8px; border-radius: 50%; border: none;
        background: rgba(255,255,255,0.5); cursor: pointer; padding: 0;
        transition: background 0.3s;
    }
    .dc-ss-dot.active { background: white; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.dc-slideshow').forEach(ss => {
        const slides = ss.querySelectorAll('.dc-ss-slide');
        const dots = ss.querySelectorAll('.dc-ss-dot');
        if (slides.length <= 1) return;

        let current = 0;
        const interval = parseInt(ss.style.getPropertyValue('--ss-interval')) || 6000;

        function goTo(idx) {
            slides[current].classList.remove('active');
            dots[current]?.classList.remove('active');
            current = idx % slides.length;
            slides[current].classList.add('active');
            dots[current]?.classList.add('active');
        }

        const timer = setInterval(() => goTo(current + 1), interval);

        dots.forEach(dot => dot.addEventListener('click', () => {
            clearInterval(timer);
            goTo(parseInt(dot.dataset.slide));
        }));
    });
});
</script>
@endonce
@endif
