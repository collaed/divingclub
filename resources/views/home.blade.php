<x-layout :title="__('Home')">
    {{-- Hero slideshow — best public photos with Ken Burns effect --}}
    @if($heroPhotos->count())
        <x-slideshow :photos="$heroPhotos" height="350px" :interval="7000" :rounded="true">
            <div class="text-center px-4">
                <h2 class="fw-bold mb-2">Club Européen de Plongée</h2>
                <p class="mb-0 fs-5">{{ __('Dive with us in Luxembourg') }} 🤿</p>
            </div>
        </x-slideshow>
        <div class="mb-4"></div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <h4 class="mb-4">{{ __('Welcome to DivingClub') }}</h4>
            @forelse($articles as $article)
                @php $m = $article->typeMeta(); @endphp
                <div class="card dc-card mb-4" style="border-left: 4px solid {{ $m['color'] }};">
                    @if($article->featured_image)
                        <img src="{{ asset('storage/' . $article->featured_image) }}" class="card-img-top" alt="{{ $article->title }}">
                    @endif
                    <div class="card-body">
                        <span class="badge mb-2" style="background:{{ $m['color'] }}">{{ $m['icon'] }} {{ __($m['label']) }}</span>
                        @if($article->vote_id) <span class="badge bg-info">🗳️ {{ __('Vote') }}</span> @endif
                        <h5 class="card-title">{{ $article->title }}</h5>
                        <p class="card-text">{!! Str::limit(strip_tags($article->body), 300) !!}</p>
                        <a href="{{ route('article.show', $article->slug) }}" class="btn btn-outline-primary btn-sm">{{ __('Read more') }}</a>
                    </div>
                    <div class="card-footer text-muted small">
                        {{ $article->created_at->format('d/m/Y') }} — {{ $article->author?->name }}
                    </div>
                </div>
            @empty
                <div class="card dc-card">
                    <div class="card-body text-center py-5">
                        <h5>🤿 {{ __('Welcome to DivingClub') }}</h5>
                        <p class="text-muted">{{ __('Your diving club management system is ready.') }}</p>
                    </div>
                </div>
            @endforelse
        </div>
        <div class="col-lg-4">
            <div class="card dc-card mb-4">
                <div class="card-header">{{ __('Quick Links') }}</div>
                <div class="list-group list-group-flush">
                    @foreach($links as $link)
                        <a href="{{ $link->url }}" class="list-group-item list-group-item-action" target="_blank">{{ $link->title }}</a>
                    @endforeach
                    @if($links->isEmpty())
                        <div class="list-group-item text-muted">{{ __('No links yet.') }}</div>
                    @endif
                </div>
            </div>

            {{-- Photo gallery widget --}}
            @if($heroPhotos->count())
                <div class="card dc-card mb-4">
                    <div class="card-header">📸 {{ __('Recent Photos') }}</div>
                    <div class="card-body p-0">
                        <x-slideshow :photos="$heroPhotos" height="200px" :interval="5000" :rounded="false" />
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layout>
