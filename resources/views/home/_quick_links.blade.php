<div class="card dc-card mb-4">
    <div class="card-header">{{ __('Quick Links') }}</div>
    <div class="list-group list-group-flush">
        @foreach($widget['data']['links'] ?? [] as $link)
            <a href="{{ $link->url }}" class="list-group-item list-group-item-action" target="_blank">{{ $link->title }}</a>
        @endforeach
        @if(($widget['data']['links'] ?? collect())->isEmpty())
            <div class="list-group-item text-muted">{{ __('No links yet.') }}</div>
        @endif
    </div>
</div>
