<div class="card dc-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>@icon('🏷️') {{ __('Classifieds') }}</span>
        <a href="{{ route('classifieds.index') }}" class="btn btn-sm btn-outline-primary py-0">{{ __('All') }}</a>
    </div>
    <div class="list-group list-group-flush">
        @forelse($widget['data']['classifieds'] ?? [] as $ad)
            <a href="{{ route('article.show', $ad->slug) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                @if($ad->featured_image)
                    <img src="{{ asset('storage/' . $ad->featured_image) }}" alt="" style="width:72px;height:72px;object-fit:cover;flex-shrink:0;border-radius:6px">
                @else
                    <div class="d-flex align-items-center justify-content-center bg-light" style="width:72px;height:72px;flex-shrink:0;border-radius:6px;font-size:1.5rem">🏷️</div>
                @endif
                <div class="min-width-0">
                    <div class="fw-semibold text-truncate" style="font-size:.9rem">{{ $ad->title }}</div>
                    <small class="text-muted">{{ $ad->author?->name }} · {{ $ad->created_at->format('d/m/Y') }}</small>
                </div>
            </a>
        @empty
            <div class="list-group-item text-muted small">{{ __('No classifieds yet.') }}</div>
        @endforelse
    </div>
</div>
