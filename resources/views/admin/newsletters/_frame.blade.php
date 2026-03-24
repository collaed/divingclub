{{--
    Newsletter frame partial — builds the visual layout from sliced images or CSS gradients.
    Variables: $theme (string), $slot1, $slot2, $slot3, $slot4, $slot5 (HTML strings)
--}}
@php
    $basePath = '/images/newsletter/bulles';
    $isBulles = ($theme ?? 'default-bulles') === 'default-bulles';

    $themeStyles = [
        'gradient-abyss'  => ['bg' => 'linear-gradient(160deg,#0a0a2e,#1a1a5e 30%,#0d2b45 60%,#000428)', 'title' => '#7eb8da', 'accent' => '#1a237e'],
        'gradient-coral'  => ['bg' => 'linear-gradient(160deg,#1a3a5c,#0e4d6e 25%,#2d6a7a 50%,#c0392b 80%,#e74c3c)', 'title' => '#f5c77e', 'accent' => '#c0392b'],
        'gradient-arctic' => ['bg' => 'linear-gradient(160deg,#37474f,#455a64 25%,#546e7a 50%,#78909c 75%,#b0bec5)', 'title' => '#e0e0e0', 'accent' => '#546e7a'],
    ];
    $ts = $themeStyles[$theme] ?? null;
@endphp

<div class="newsletter-frame rounded overflow-hidden" style="max-width:650px;margin:0 auto;{{ $ts ? 'background:'.$ts['bg'] : '' }}">

    {{-- HEADER --}}
    @if($isBulles)
        <img src="{{ asset($basePath.'/header.jpg') }}" class="w-100 d-block" alt="" style="height:auto">
    @else
        <div style="padding:40px 20px;text-align:center;background:rgba(0,0,0,0.2)">
            <h2 style="color:{{ $ts['title'] ?? '#d4a843' }};text-shadow:2px 2px 6px rgba(0,0,0,0.7);font-family:Georgia,serif;margin:0">
                Bulles et Aventures
            </h2>
            <div style="color:{{ $ts['title'] ?? '#d4a843' }};opacity:0.8;font-style:italic;font-family:Georgia,serif">
                votre newsletter plongée
            </div>
        </div>
    @endif

    {{-- ROW 1: slots 1 & 2 --}}
    <div style="display:flex;align-items:stretch">
        @if($isBulles)
            <img src="{{ asset($basePath.'/row1-left.jpg') }}" style="width:45px;flex-shrink:0" alt="">
        @else
            <div style="width:20px;flex-shrink:0"></div>
        @endif

        <div style="flex:1;min-width:0;padding:6px 0">{!! $slot1 !!}</div>

        @if($isBulles)
            <img src="{{ asset($basePath.'/row1-center.jpg') }}" style="width:45px;flex-shrink:0" alt="">
        @else
            <div style="width:12px;flex-shrink:0"></div>
        @endif

        <div style="flex:1;min-width:0;padding:6px 0">{!! $slot2 !!}</div>

        @if($isBulles)
            <img src="{{ asset($basePath.'/row1-right.jpg') }}" style="width:44px;flex-shrink:0" alt="">
        @else
            <div style="width:20px;flex-shrink:0"></div>
        @endif
    </div>

    {{-- HORIZONTAL SEPARATOR --}}
    @if($isBulles)
        <img src="{{ asset($basePath.'/h-separator.jpg') }}" class="w-100 d-block" alt="" style="height:auto">
    @else
        <div style="height:12px"></div>
    @endif

    {{-- ROW 2: slots 3 & 4 --}}
    <div style="display:flex;align-items:stretch">
        @if($isBulles)
            <img src="{{ asset($basePath.'/row2-left.jpg') }}" style="width:45px;flex-shrink:0" alt="">
        @else
            <div style="width:20px;flex-shrink:0"></div>
        @endif

        <div style="flex:1;min-width:0;padding:6px 0">{!! $slot3 !!}</div>

        @if($isBulles)
            <img src="{{ asset($basePath.'/row2-center.jpg') }}" style="width:45px;flex-shrink:0" alt="">
        @else
            <div style="width:12px;flex-shrink:0"></div>
        @endif

        <div style="flex:1;min-width:0;padding:6px 0">{!! $slot4 !!}</div>

        @if($isBulles)
            <img src="{{ asset($basePath.'/row2-right.jpg') }}" style="width:44px;flex-shrink:0" alt="">
        @else
            <div style="width:20px;flex-shrink:0"></div>
        @endif
    </div>

    {{-- FOOTER with slot 5 centered --}}
    @if($isBulles)
        <div class="position-relative">
            <img src="{{ asset($basePath.'/footer.jpg') }}" class="w-100 d-block" alt="" style="height:auto">
            <div class="position-absolute" style="top:15%;left:5%;width:55%">{!! $slot5 !!}</div>
        </div>
    @else
        <div style="padding:15px 20px;display:flex;justify-content:center">
            <div style="width:55%">{!! $slot5 !!}</div>
        </div>
    @endif
</div>
