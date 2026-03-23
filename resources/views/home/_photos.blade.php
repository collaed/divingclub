@php $zone = $zone ?? $widget['zone'] ?? 'sidebar'; @endphp
@if(($widget['data']['photos'] ?? collect())->count())
<div class="card dc-card mb-4">
    <div class="card-header">@icon('📸') {{ __('Recent Photos') }}</div>
    <div class="card-body p-0">
        @if($zone === 'main')
            {{-- Wide zone: responsive grid --}}
            <div class="row g-1 p-2">
                @foreach($widget['data']['photos'] as $photo)
                    @php $url = $photo->url ?? asset('storage/' . $photo->path); @endphp
                    <div class="col-6 col-md-4 col-lg-3">
                        <div style="aspect-ratio:4/3;position:relative;overflow:hidden;border-radius:4px;background:#1a1a2e">
                            <div style="position:absolute;inset:-10px;background:url('{{ $url }}') center/cover;filter:blur(10px) brightness(0.6);transform:scale(1.1)"></div>
                            <div style="position:absolute;inset:0;background:url('{{ $url }}') center/contain no-repeat"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- Sidebar: vertical slideshow --}}
            <x-slideshow :photos="$widget['data']['photos']" height="200px" :interval="5000" :rounded="false" />
        @endif
    </div>
</div>
@endif
