<x-layout :title="__('Classifieds')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">@icon('🏷️') {{ __('Classifieds') }}</h4>
        <a href="{{ route('classifieds.create') }}" class="btn btn-primary">{{ __('Post a Classified') }}</a>
    </div>

    <form method="GET" action="{{ route('classifieds.index') }}" class="mb-4 d-flex gap-2" style="max-width:400px">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ __('Search classifieds…') }}" value="{{ request('search') }}">
        <button class="btn btn-sm btn-outline-primary">{{ __('Search') }}</button>
        @if(request('search'))
            <a href="{{ route('classifieds.index') }}" class="btn btn-sm btn-outline-secondary">✕</a>
        @endif
    </form>

    {{-- My classifieds --}}
    @if($mine->count())
        <div class="card dc-card mb-4">
            <div class="card-header">{{ __('My Classifieds') }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach($mine as $a)
                        <tr class="{{ $a->isExpired() ? 'text-muted' : '' }}">
                            <td>{{ $a->title }}</td>
                            <td>{{ $a->expires_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>
                                @if($a->isExpired()) <span class="badge bg-secondary">{{ __('Expired') }}</span>
                                @elseif($a->expires_at?->diffInDays(now()) <= 7) <span class="badge bg-warning text-dark">{{ __('Expiring soon') }}</span>
                                @else <span class="badge bg-success">{{ __('Active') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($a->isExpired())
                                    <form method="POST" action="{{ route('classifieds.extend', $a) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success">{{ __('Renew') }}</button></form>
                                @else
                                    <form method="POST" action="{{ route('classifieds.extend', $a) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-info">{{ __('Extend') }}</button></form>
                                @endif
                                <a href="{{ route('classifieds.edit', $a) }}" class="btn btn-sm btn-outline-primary">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('classifieds.destroy', $a) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete?') }}')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button></form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- All active classifieds --}}
    <div class="row">
        @forelse($classifieds as $ad)
            <div class="col-md-6 mb-4">
                <div class="card dc-card h-100" style="border-left: 4px solid #ffc107;">
                    @if($ad->featured_image)
                        <img src="{{ asset('storage/' . $ad->featured_image) }}" class="card-img-top" alt="{{ $ad->title }}" style="max-height:200px; object-fit:cover;">
                    @endif
                    <div class="card-body">
                        <h6 class="card-title">{{ $ad->title }}</h6>
                        <p class="card-text small">{{ Str::limit(strip_tags($ad->body), 200) }}</p>
                        <a href="{{ route('article.show', $ad->slug) }}" class="btn btn-sm btn-outline-primary">{{ __('Read more') }}</a>
                    </div>
                    <div class="card-footer text-muted small d-flex justify-content-between">
                        <span>{{ $ad->author?->name }} · {{ $ad->created_at->format('d/m/Y') }}</span>
                        @if($ad->expires_at)
                            <span>{{ __('Expires') }}: {{ $ad->expires_at->format('d/m/Y') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card dc-card"><div class="card-body text-center py-4 text-muted">{{ __('No classifieds yet. Be the first to post!') }}</div></div>
            </div>
        @endforelse
    </div>
    {{ $classifieds->links() }}
</x-layout>
