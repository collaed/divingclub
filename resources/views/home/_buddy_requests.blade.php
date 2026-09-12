<div class="card dc-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>@icon('🤝') {{ __('Buddy Requests') }}</span>
        <a href="{{ route('buddies.index') }}" class="btn btn-sm btn-outline-primary py-0">{{ __('All') }}</a>
    </div>
    <div class="list-group list-group-flush">
        @forelse($widget['data']['requests'] ?? [] as $req)
            @php $needInfo = \App\Models\BuddyRequest::NEED_TYPES[$req->need_type] ?? $req->need_type; @endphp
            <a href="{{ route('buddies.index') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                @if($req->user->detail?->avatar_path)
                    <img src="{{ asset('storage/' . $req->user->detail->avatar_path) }}" alt="" class="rounded-circle" style="width:72px;height:72px;object-fit:cover;flex-shrink:0">
                @else
                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center fw-bold" style="width:72px;height:72px;flex-shrink:0">
                        {{ strtoupper(substr($req->user->detail?->first_name ?? '?', 0, 1) . substr($req->user->detail?->last_name ?? '', 0, 1)) }}
                    </div>
                @endif
                <div class="min-width-0">
                    <div class="fw-semibold text-truncate" style="font-size:.9rem">{{ $req->locationLabel() }}</div>
                    <small class="text-muted">{{ $req->dive_date->format('d/m/Y') }} · {{ $needInfo }} · {{ $req->user->detail?->first_name }}</small>
                </div>
            </a>
        @empty
            <div class="list-group-item text-muted small">{{ __('No active buddy requests.') }}</div>
        @endforelse
    </div>
</div>
