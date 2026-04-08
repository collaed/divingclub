@php $zone = $zone ?? $widget['zone'] ?? 'sidebar'; $photoUrls = ($widget['data']['photos'] ?? collect())->map(fn ($p) => $p->url ?? asset('storage/' . $p->path))->values(); @endphp
@if($photoUrls->count())
<div class="card dc-card mb-4">
    <div class="card-header d-flex justify-content-between">
        @icon('📸') {{ __('Recent Photos') }}
        <a href="{{ route('gallery') }}" class="small">{{ __('View all') }} →</a>
    </div>
    <div class="card-body p-0">
        @if($zone === 'main')
            <div class="row g-1 p-2">
                @foreach($photoUrls as $i => $url)
                    <div class="col-6 col-md-4 col-lg-3">
                        <div style="aspect-ratio:4/3;position:relative;overflow:hidden;border-radius:4px;background:#1a1a2e;cursor:pointer" onclick="openGallery('hp',{{ $i }})">
                            <div style="position:absolute;inset:-10px;background:url('{{ $url }}') center/cover;filter:blur(10px) brightness(0.6);transform:scale(1.1)"></div>
                            <div style="position:absolute;inset:0;background:url('{{ $url }}') center/contain no-repeat"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div onclick="openGallery('hp',0)" style="cursor:pointer">
                <x-slideshow :photos="$widget['data']['photos']" height="200px" :interval="5000" :rounded="false" />
            </div>
        @endif
    </div>
</div>
@include('components.photo-gallery', ['galleryId' => 'hp'])
<script>document.getElementById('pg-hp').dataset.photos=@json($photoUrls);</script>
@endif
