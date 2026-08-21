{{--
    Reusable article card for newsletter email slots.
    Variables: $pos, $slotArticles, $locale, $appUrl, $articleBaseUrl, $colors, $readMore, $fontFamily
--}}
@if(isset($slotArticles[$pos]))
    @php
        $article = $slotArticles[$pos]['article'];
        $slotMeta = $slotArticles[$pos];
        $t = $article->translated($locale);
        $baseUrl = $articleBaseUrl ?? $appUrl;
        $url = $slotMeta['custom_url'] ?? ($baseUrl . '/article/' . $article->slug);
        $icon = \App\Models\Article::TYPES[$article->article_type]['icon'] ?? '📄';
        $hasImg = (bool) $article->featured_image;
        $teaser = $slotMeta['teaser'] ?? '';
        if (!$teaser) {
            $teaser = Str::limit(strip_tags($t['body']), $hasImg ? 100 : 180);
        }
        $enUrl = $baseUrl . '/article/' . $article->slug;
        $hasEnTranslation = $article->translations->contains('locale', 'en');
        $cardFont = $fontFamily ?? "'IBM Plex Sans', Arial, sans-serif";
    @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $colors['card'] }};border-radius:6px;overflow:hidden">
        @if($hasImg)
        <tr>
            <td style="padding:0;font-size:0;line-height:0">
                <a href="{{ $url }}" style="text-decoration:none">
                    <img src="{{ $appUrl }}/storage/{{ $article->featured_image }}" width="260" style="width:100%;max-height:120px;display:block;border:0" alt="">
                </a>
            </td>
        </tr>
        @endif
        <tr>
            <td style="padding:12px;font-family:{{ $cardFont }};{{ $pos <= 4 ? 'height:150px;' : '' }}" valign="top">
                <a href="{{ $url }}" style="text-decoration:none;color:{{ $colors['title'] }};font-family:{{ $cardFont }}">
                    <strong style="font-size:14px;line-height:1.3;display:block;margin-bottom:6px">{{ $icon }} {{ e($t['title']) }}</strong>
                </a>
                <p style="margin:0 0 8px;font-size:12px;color:{{ $colors['text'] }};line-height:1.5;font-family:{{ $cardFont }}">{{ $teaser }}</p>
                <table width="100%" cellpadding="0" cellspacing="0"><tr>
                    <td align="left" style="padding:0">
                        @if($hasEnTranslation)
                            <a href="{{ $enUrl }}" style="color:#999;font-size:10px;text-decoration:none">EN ›</a>
                        @endif
                    </td>
                    <td align="right" style="padding:0">
                        <a href="{{ $url }}" style="color:#0077be;font-size:11px;text-decoration:none;font-weight:bold">{{ $readMore }}</a>
                    </td>
                </tr></table>
            </td>
        </tr>
    </table>
@else
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:rgba(255,255,255,0.3);border-radius:6px;min-height:80px">
    <tr><td align="center" valign="middle" style="padding:20px;color:#ccc;font-size:11px">—</td></tr>
    </table>
@endif
