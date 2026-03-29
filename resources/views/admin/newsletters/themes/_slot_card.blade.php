{{--
    Reusable article card for newsletter email slots.
    Variables: $pos, $slotArticles, $locale, $appUrl, $colors, $readMore
--}}
@if(isset($slotArticles[$pos]))
    @php
        $article = $slotArticles[$pos]['article'];
        $t = $article->translated($locale);
        $url = $appUrl . '/article/' . $article->slug;
        $icon = \App\Models\Article::TYPES[$article->article_type]['icon'] ?? '📄';
        $hasImg = (bool) $article->featured_image;
        $excerpt = Str::limit(strip_tags($t['body']), $hasImg ? 100 : 180);
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
            <td style="padding:10px">
                <a href="{{ $url }}" style="text-decoration:none;color:{{ $colors['title'] }}">
                    <strong style="font-size:13px;line-height:1.3;display:block;margin-bottom:6px">{{ $icon }} {{ e($t['title']) }}</strong>
                </a>
                <p style="margin:0 0 8px;font-size:11px;color:{{ $colors['text'] }};line-height:1.4">{{ $excerpt }}</p>
                <a href="{{ $url }}" style="color:#0077be;font-size:11px;text-decoration:none;font-weight:bold">{{ $readMore }}</a>
            </td>
        </tr>
    </table>
@else
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:rgba(255,255,255,0.3);border-radius:6px;min-height:80px">
    <tr><td align="center" valign="middle" style="padding:20px;color:#ccc;font-size:11px">—</td></tr>
    </table>
@endif
