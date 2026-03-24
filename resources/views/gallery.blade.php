{{-- Photo gallery — lightweight card-stack previews per event | ClubCEP.eu --}}
<x-layout :title="__('Photo Gallery')">
    <h4 class="mb-4">@icon('📸') {{ __('Photo Gallery') }}</h4>

    @forelse($photos as $eventTitle => $eventPhotos)
        @php $items = $eventPhotos->values()->take(5); @endphp
        <div class="card dc-card mb-4">
            <div class="card-header d-flex justify-content-between">
                <span>{{ $eventTitle }}</span>
                <span class="badge bg-secondary">{{ $eventPhotos->count() }} {{ __('photos') }}</span>
            </div>
            <div class="card-body d-flex justify-content-center py-4">
                <div class="photo-stack" style="position:relative;width:280px;height:200px">
                    @foreach($items as $i => $photo)
                        @php
                            $url = is_string($photo) ? $photo : ($photo->url ?? $photo['url'] ?? asset('storage/' . ($photo->path ?? $photo['path'] ?? '')));
                            $rot = ($i - floor($items->count() / 2)) * 8;
                            $z = $items->count() - $i;
                        @endphp
                        <img src="{{ $url }}" alt="" loading="lazy"
                             style="position:absolute;top:50%;left:50%;width:200px;height:150px;object-fit:cover;border-radius:6px;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.25);transform:translate(-50%,-50%) rotate({{ $rot }}deg);z-index:{{ $z }};transition:transform .3s"
                             onmouseenter="this.style.zIndex=99;this.style.transform='translate(-50%,-55%) rotate(0deg) scale(1.1)'"
                             onmouseleave="this.style.zIndex={{ $z }};this.style.transform='translate(-50%,-50%) rotate({{ $rot }}deg)'">
                    @endforeach
                </div>
            </div>
        </div>
    @empty
        <div class="card dc-card">
            <div class="card-body text-center py-5 text-muted">
                @icon('📷') {{ __('No photos yet. Photos will appear here after events.') }}
            </div>
        </div>
    @endforelse
</x-layout>
