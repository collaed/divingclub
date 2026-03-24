{{--
    Newsletter frame partial — builds the visual layout from sliced images or CSS gradients.
    Variables: $theme (string), $slot1, $slot2, $slot3, $slot4, $slot5 (HTML strings)
--}}
@php
    $basePath = '/images/newsletter/bulles';
    $isBulles = ($theme ?? 'default-bulles') === 'default-bulles';

    $themeStyles = [
        'gradient-abyss'  => ['bg' => 'linear-gradient(160deg,#0a0a2e,#1a1a5e 30%,#0d2b45 60%,#000428)', 'title' => '#7eb8da'],
        'gradient-coral'  => ['bg' => 'linear-gradient(160deg,#1a3a5c,#0e4d6e 25%,#2d6a7a 50%,#c0392b 80%,#e74c3c)', 'title' => '#f5c77e'],
        'gradient-arctic' => ['bg' => 'linear-gradient(160deg,#37474f,#455a64 25%,#546e7a 50%,#78909c 75%,#b0bec5)', 'title' => '#e0e0e0'],
    ];
    $ts = $themeStyles[$theme] ?? null;
@endphp

<div class="newsletter-frame rounded overflow-hidden" style="max-width:650px;margin:0 auto;{{ $ts ? 'background:'.$ts['bg'] : '' }}">

    {{-- HEADER --}}
    @if($isBulles)
        <img src="{{ asset($basePath.'/header.jpg') }}" class="w-100 d-block" alt="">
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
    <div style="display:flex;height:240px;overflow:hidden">
        @if($isBulles)
            <div style="width:45px;flex-shrink:0;overflow:hidden"><img src="{{ asset($basePath.'/row1-left.jpg') }}" style="width:100%;height:100%;object-fit:cover" alt=""></div>
        @else
            <div style="width:20px;flex-shrink:0"></div>
        @endif

        <div style="flex:1;min-width:0;padding:4px;overflow:hidden">{!! $slot1 !!}</div>

        @if($isBulles)
            <div style="width:45px;flex-shrink:0;overflow:hidden"><img src="{{ asset($basePath.'/row1-center.jpg') }}" style="width:100%;height:100%;object-fit:cover" alt=""></div>
        @else
            <div style="width:12px;flex-shrink:0"></div>
        @endif

        <div style="flex:1;min-width:0;padding:4px;overflow:hidden">{!! $slot2 !!}</div>

        @if($isBulles)
            <div style="width:44px;flex-shrink:0;overflow:hidden"><img src="{{ asset($basePath.'/row1-right.jpg') }}" style="width:100%;height:100%;object-fit:cover" alt=""></div>
        @else
            <div style="width:20px;flex-shrink:0"></div>
        @endif
    </div>

    {{-- HORIZONTAL SEPARATOR --}}
    @if($isBulles)
        <img src="{{ asset($basePath.'/h-separator.jpg') }}" class="w-100 d-block" alt="">
    @else
        <div style="height:12px"></div>
    @endif

    {{-- ROW 2: slots 3 & 4 --}}
    <div style="display:flex;height:220px;overflow:hidden">
        @if($isBulles)
            <div style="width:45px;flex-shrink:0;overflow:hidden"><img src="{{ asset($basePath.'/row2-left.jpg') }}" style="width:100%;height:100%;object-fit:cover" alt=""></div>
        @else
            <div style="width:20px;flex-shrink:0"></div>
        @endif

        <div style="flex:1;min-width:0;padding:4px;overflow:hidden">{!! $slot3 !!}</div>

        @if($isBulles)
            <div style="width:45px;flex-shrink:0;overflow:hidden"><img src="{{ asset($basePath.'/row2-center.jpg') }}" style="width:100%;height:100%;object-fit:cover" alt=""></div>
        @else
            <div style="width:12px;flex-shrink:0"></div>
        @endif

        <div style="flex:1;min-width:0;padding:4px;overflow:hidden">{!! $slot4 !!}</div>

        @if($isBulles)
            <div style="width:44px;flex-shrink:0;overflow:hidden"><img src="{{ asset($basePath.'/row2-right.jpg') }}" style="width:100%;height:100%;object-fit:cover" alt=""></div>
        @else
            <div style="width:20px;flex-shrink:0"></div>
        @endif
    </div>

    {{-- FOOTER with slot 5 centered --}}
    @if($isBulles)
        <div style="position:relative">
            <img src="{{ asset($basePath.'/footer.jpg') }}" class="w-100 d-block" alt="">
            <div style="position:absolute;top:12%;left:0;right:0;display:flex;justify-content:center">{!! $slot5 !!}</div>
        </div>
    @else
        <div style="padding:15px 20px;display:flex;justify-content:center">{!! $slot5 !!}</div>
    @endif
</div>
