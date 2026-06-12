<div class="card dc-card mb-4">
    <div class="card-header">{{ __('Quick Links') }}</div>
    <div class="list-group list-group-flush">
        @foreach($widget['data']['links'] ?? [] as $link)
            <a href="{{ $link->url }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2" target="_blank" rel="noopener">
                @if($link->image_url)
                    <img src="{{ $link->image_url }}" alt="" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;padding:4px;background:#fff;border-radius:6px">
                @endif
                <div>
                    <strong class="small">{{ $link->title }}</strong>
                    @if($link->description)
                        <br><span class="text-muted" style="font-size:.75rem">{{ $link->description }}</span>
                    @endif
                </div>
            </a>
        @endforeach
        @if(($widget['data']['links'] ?? collect())->isEmpty())
            <div class="list-group-item text-muted">{{ __('No links yet.') }}</div>
        @endif
    </div>
</div>
