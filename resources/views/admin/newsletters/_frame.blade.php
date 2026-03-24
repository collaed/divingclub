{{--
    Newsletter frame partial.
    Variables: $theme (string), $slot1-$slot5 (HTML strings)
--}}
@php
    $isBulles = ($theme ?? 'default-bulles') === 'default-bulles';
    $themeStyles = [
        'gradient-abyss'  => ['bg' => 'linear-gradient(160deg,#0a0a2e,#1a1a5e 30%,#0d2b45 60%,#000428)', 'title' => '#7eb8da'],
        'gradient-coral'  => ['bg' => 'linear-gradient(160deg,#1a3a5c,#0e4d6e 25%,#2d6a7a 50%,#c0392b 80%,#e74c3c)', 'title' => '#f5c77e'],
        'gradient-arctic' => ['bg' => 'linear-gradient(160deg,#37474f,#455a64 25%,#546e7a 50%,#78909c 75%,#b0bec5)', 'title' => '#e0e0e0'],
    ];
    $ts = $themeStyles[$theme] ?? null;
@endphp

@if($isBulles)
    {{-- Bulles theme: single background image with absolutely positioned cards --}}
    <div class="newsletter-frame" style="position:relative;max-width:650px;margin:0 auto;font-size:0">
        <img src="{{ asset('images/newsletter/bulles-bg.jpg') }}" style="width:100%;display:block" alt="">
        {{-- Cards positioned at exact coordinates matching the white boxes in the image --}}
        <div style="position:absolute;left:12.5%;top:32.1%;width:34.5%;height:20.5%;overflow:hidden">{!! $slot1 !!}</div>
        <div style="position:absolute;left:53.0%;top:32.1%;width:34.5%;height:20.5%;overflow:hidden">{!! $slot2 !!}</div>
        <div style="position:absolute;left:12.5%;top:55.1%;width:34.5%;height:20.6%;overflow:hidden">{!! $slot3 !!}</div>
        <div style="position:absolute;left:53.0%;top:55.1%;width:34.5%;height:20.6%;overflow:hidden">{!! $slot4 !!}</div>
        <div style="position:absolute;left:22%;top:80.5%;width:36%;height:5.8%;overflow:hidden">{!! $slot5 !!}</div>
    </div>
@else
    {{-- Gradient themes: CSS-only layout --}}
    <div class="newsletter-frame rounded overflow-hidden" style="max-width:650px;margin:0 auto;background:{{ $ts['bg'] ?? '#003366' }}">
        <div style="padding:40px 20px;text-align:center">
            <h2 style="color:{{ $ts['title'] ?? '#d4a843' }};text-shadow:2px 2px 6px rgba(0,0,0,0.7);font-family:Georgia,serif;margin:0">Bulles et Aventures</h2>
            <div style="color:{{ $ts['title'] ?? '#d4a843' }};opacity:0.8;font-style:italic;font-family:Georgia,serif">votre newsletter plongée</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:0 20px">
            <div>{!! $slot1 !!}</div>
            <div>{!! $slot2 !!}</div>
            <div>{!! $slot3 !!}</div>
            <div>{!! $slot4 !!}</div>
        </div>
        <div style="padding:15px 20px;display:flex;justify-content:center">{!! $slot5 !!}</div>
    </div>
@endif
