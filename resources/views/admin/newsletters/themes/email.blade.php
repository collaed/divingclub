{{--
    Email-safe newsletter template.
    Uses table layout + sliced images for maximum email client compatibility.

    Variables:
        $newsletter, $slotArticles, $locale, $appUrl, $clubName, $theme, $monthLabel, $unsubscribeUrl
--}}
@php
    $imgBase = $appUrl . '/images/newsletter/' . ($theme ?? 'bulles');
    $isBulles = str_contains($theme ?? 'bulles', 'bulles');
    $readMore = $locale === 'fr' ? 'Lire la suite →' : __('Read more →');

    // Theme color map
    $colors = match($theme ?? 'bulles') {
        'abyss'    => ['bg' => '#0a0a2e', 'card' => '#1a1a4e', 'title' => '#7eb8da', 'text' => '#ccc'],
        'coral'    => ['bg' => '#1a3a5c', 'card' => '#0e4d6e', 'title' => '#f5c77e', 'text' => '#ddd'],
        'arctic'   => ['bg' => '#37474f', 'card' => '#455a64', 'title' => '#e0e0e0', 'text' => '#ccc'],
        default    => ['bg' => '#003366', 'card' => '#ffffff', 'title' => '#003366', 'text' => '#555'],
    };
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $newsletter->title }}</title>
    <!--[if mso]><style>table{border-collapse:collapse;}td{font-family:Arial,sans-serif;}</style><![endif]-->
</head>
<body style="margin:0;padding:0;background:#e8f0f5;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%">

{{-- Outer wrapper --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e8f0f5">
<tr><td align="center" style="padding:20px 10px">

{{-- Main container: 600px --}}
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:{{ $colors['bg'] }}">

    {{-- HEADER --}}
    <tr>
        <td align="center" style="padding:0">
            <img src="{{ $imgBase }}/header.jpg" width="600" style="width:100%;max-width:600px;display:block;border:0" alt="{{ e($newsletter->title) }}">
            @if($monthLabel)
                {{-- Month label rendered as text below header for reliability --}}
            @endif
        </td>
    </tr>

    {{-- MONTH LABEL (skip for bulles theme — header image already contains it) --}}
    @if($monthLabel && !$isBulles)
    <tr>
        <td align="center" style="padding:8px 0 4px;background:{{ $colors['bg'] }}">
            <span style="font-family:Georgia,serif;font-size:18px;color:#d4a843;font-style:italic">{{ ucfirst($monthLabel) }}</span>
        </td>
    </tr>
    @endif

    {{-- ROW 1: Slots 1 & 2 --}}
    <tr>
        <td style="padding:0">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                {{-- Left decorative border --}}
                @if($isBulles)
                <td width="45" style="background-image:url('{{ $imgBase }}/row1-left.jpg');background-size:cover;background-position:center" valign="top">
                    <img src="{{ $imgBase }}/row1-left.jpg" width="45" style="display:block;width:45px" alt="">
                </td>
                @endif

                {{-- Slot 1 --}}
                <td width="50%" valign="top" style="padding:8px">
                    @include('admin.newsletters.themes._slot_card', ['pos' => 1, 'slotArticles' => $slotArticles, 'locale' => $locale, 'appUrl' => $appUrl, 'colors' => $colors, 'readMore' => $readMore])
                </td>

                {{-- Center decorative border --}}
                @if($isBulles)
                <td width="45" style="background-image:url('{{ $imgBase }}/row1-center.jpg');background-size:cover;background-position:center" valign="top">
                    <img src="{{ $imgBase }}/row1-center.jpg" width="45" style="display:block;width:45px" alt="">
                </td>
                @endif

                {{-- Slot 2 --}}
                <td width="50%" valign="top" style="padding:8px">
                    @include('admin.newsletters.themes._slot_card', ['pos' => 2, 'slotArticles' => $slotArticles, 'locale' => $locale, 'appUrl' => $appUrl, 'colors' => $colors, 'readMore' => $readMore])
                </td>

                {{-- Right decorative border --}}
                @if($isBulles)
                <td width="44" style="background-image:url('{{ $imgBase }}/row1-right.jpg');background-size:cover;background-position:center" valign="top">
                    <img src="{{ $imgBase }}/row1-right.jpg" width="44" style="display:block;width:44px" alt="">
                </td>
                @endif
            </tr>
            </table>
        </td>
    </tr>

    {{-- SEPARATOR --}}
    @if($isBulles)
    <tr>
        <td style="padding:0;font-size:0;line-height:0">
            <img src="{{ $imgBase }}/h-separator.jpg" width="600" style="width:100%;max-width:600px;display:block;border:0" alt="">
        </td>
    </tr>
    @else
    <tr><td style="padding:6px 0"></td></tr>
    @endif

    {{-- ROW 2: Slots 3 & 4 --}}
    <tr>
        <td style="padding:0">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                @if($isBulles)
                <td width="45" style="background-image:url('{{ $imgBase }}/row2-left.jpg');background-size:cover;background-position:center" valign="top">
                    <img src="{{ $imgBase }}/row2-left.jpg" width="45" style="display:block;width:45px" alt="">
                </td>
                @endif

                <td width="50%" valign="top" style="padding:8px">
                    @include('admin.newsletters.themes._slot_card', ['pos' => 3, 'slotArticles' => $slotArticles, 'locale' => $locale, 'appUrl' => $appUrl, 'colors' => $colors, 'readMore' => $readMore])
                </td>

                @if($isBulles)
                <td width="45" style="background-image:url('{{ $imgBase }}/row2-center.jpg');background-size:cover;background-position:center" valign="top">
                    <img src="{{ $imgBase }}/row2-center.jpg" width="45" style="display:block;width:45px" alt="">
                </td>
                @endif

                <td width="50%" valign="top" style="padding:8px">
                    @include('admin.newsletters.themes._slot_card', ['pos' => 4, 'slotArticles' => $slotArticles, 'locale' => $locale, 'appUrl' => $appUrl, 'colors' => $colors, 'readMore' => $readMore])
                </td>

                @if($isBulles)
                <td width="44" style="background-image:url('{{ $imgBase }}/row2-right.jpg');background-size:cover;background-position:center" valign="top">
                    <img src="{{ $imgBase }}/row2-right.jpg" width="44" style="display:block;width:44px" alt="">
                </td>
                @endif
            </tr>
            </table>
        </td>
    </tr>

    {{-- SLOT 5: Bottom banner --}}
    <tr>
        <td align="center" style="padding:10px 20px">
            @if(isset($slotArticles[5]))
                @php
                    $a5 = $slotArticles[5]['article'];
                    $t5 = $a5->translated($locale);
                    $s5 = $slotArticles[5];
                    $url5 = $s5['custom_url'] ?? (($articleBaseUrl ?? $appUrl) . '/article/' . $a5->slug);
                    $icon5 = \App\Models\Article::TYPES[$a5->article_type]['icon'] ?? '📄';
                @endphp
                <table role="presentation" cellpadding="0" cellspacing="0" style="background:rgba(255,255,255,0.9);border-radius:6px">
                <tr>
                    <td style="padding:10px 24px">
                        <a href="{{ $url5 }}" style="color:{{ $colors['title'] }};font-weight:bold;text-decoration:none;font-size:14px;font-family:Georgia,serif">{{ $icon5 }} {{ e($t5['title']) }}</a>
                    </td>
                </tr>
                </table>
            @endif
        </td>
    </tr>

    {{-- SCATTERED DECORATIONS (rendered as small accent images) --}}
    @if(!empty($decorations))
    <tr>
        <td style="padding:4px 10px;text-align:center;font-size:0;line-height:0">
            @foreach(collect($decorations)->take(8) as $dec)
                <img src="{{ $appUrl }}{{ $dec['src'] }}" width="32" height="32" style="display:inline-block;width:32px;height:32px;margin:2px 6px;opacity:0.35" alt="">
            @endforeach
        </td>
    </tr>
    @endif

    {{-- FOOTER IMAGE --}}
    <tr>
        <td style="padding:0;font-size:0;line-height:0">
            <img src="{{ $imgBase }}/footer.jpg" width="600" style="width:100%;max-width:600px;display:block;border:0" alt="">
        </td>
    </tr>

</table>

{{-- TEXT FOOTER --}}
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%">
<tr>
    <td align="center" style="padding:20px 10px;font-size:11px;color:#999;font-family:Arial,sans-serif">
        {{ e($clubName) }} — <a href="{{ $appUrl }}" style="color:#999">{{ $appUrl }}</a><br>
        @if($unsubscribeUrl ?? false)
            <a href="{{ $unsubscribeUrl }}" style="color:#999;text-decoration:underline">{{ $locale === 'fr' ? 'Se désabonner' : __('Unsubscribe') }}</a>
        @else
            <span style="font-size:10px;color:#bbb">{{ $locale === 'fr' ? 'Pour ne plus recevoir cette newsletter, répondez avec "désabonner".' : 'To unsubscribe, reply with "unsubscribe".' }}</span>
        @endif
    </td>
</tr>
</table>

</td></tr>
</table>

</body>
</html>
