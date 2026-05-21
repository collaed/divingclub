{{-- Photo gallery — responsive grid of event cards with fanned thumbnails | ClubCEP.eu --}}
<x-layout :title="__('Photo Gallery')">
    <h4 class="mb-4">@icon('📸') {{ __('Photo Gallery') }}</h4>

    @if($events->count())
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
            @foreach($events as $ev)
                <div class="col">
                    <a href="{{ $ev->event_id ? route('gallery.event', $ev->event_id) : '#' }}" class="text-decoration-none">
                    <div class="card dc-card h-100">
                        <div class="d-flex justify-content-center align-items-center py-3" style="height:160px;overflow:hidden;background:#f0f4f8">
                            @foreach($ev->photos as $i => $photo)
                                @php
                                    $url = is_string($photo) ? $photo : ($photo->url ?? $photo['url'] ?? asset('storage/' . ($photo->path ?? $photo['path'] ?? '')));
                                    $rot = ($i - floor($ev->photos->count() / 2)) * 8;
                                    $z = $ev->photos->count() - $i;
                                @endphp
                                <img src="{{ $url }}" alt="{{ __(\'Avatar\') }}" loading="lazy"
                                     style="position:absolute;width:120px;height:90px;object-fit:cover;border-radius:4px;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.2);transform:rotate({{ $rot }}deg);z-index:{{ $z }};transition:transform .2s"
                                     onmouseenter="this.style.zIndex=99;this.style.transform='rotate(0deg) scale(1.15)'"
                                     onmouseleave="this.style.zIndex={{ $z }};this.style.transform='rotate({{ $rot }}deg)'">
                            @endforeach
                        </div>
                        <div class="card-body py-2 px-2 text-center">
                            <strong class="small">{{ Str::limit($ev->title, 30) }}</strong>
                            <div class="text-muted" style="font-size:.7rem">
                                {{ $ev->count }} {{ __('photos') }}
                                · {{ $ev->latest->translatedFormat('d M Y') }}
                            </div>
                        </div>
                    </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="mt-3">{{ $events->links() }}</div>
    @else
        <div class="card dc-card">
            <div class="card-body text-center py-5 text-muted">
                @icon('📷') {{ __('No photos yet. Photos will appear here after events.') }}
            </div>
        </div>
    @endif
</x-layout>
