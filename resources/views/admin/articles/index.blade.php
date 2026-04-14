<x-layout :title="__('Articles')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">{{ __('Articles') }}</h4>
        <a href="{{ route('admin.articles.create') }}" class="btn btn-primary">{{ __('New Article') }}</a>
    </div>

    {{-- Search + type filter --}}
    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
        <form method="GET" action="{{ route('admin.articles.index') }}" class="d-flex gap-2 flex-grow-1" style="max-width:400px">
            @if(request('type'))<input type="hidden" name="type" value="{{ request('type') }}">@endif
            <input type="text" name="search" data-instant-search="table-articles" class="form-control form-control-sm" placeholder="{{ __('Search articles in all languages…') }}" value="{{ request('search') }}">
            <button class="btn btn-sm btn-outline-primary">{{ __('Search') }}</button>
            @if(request('search'))
                <a href="{{ route('admin.articles.index', request()->only('type')) }}" class="btn btn-sm btn-outline-secondary">✕</a>
            @endif
        </form>
    </div>

    <div class="mb-3 d-flex flex-wrap gap-1">
        <a href="{{ route('admin.articles.index', request()->only('search')) }}" class="btn btn-sm {{ !request('type') ? 'btn-primary' : 'btn-outline-secondary' }}">{{ __('All') }}</a>
        @foreach(\App\Models\Article::TYPES as $key => $meta)
            <a href="{{ route('admin.articles.index', ['type' => $key] + request()->only('search')) }}" class="btn btn-sm {{ request('type') === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $meta['icon'] }} {{ __($meta['label']) }}
            </a>
        @endforeach
    </div>

    @if(request('search'))
        <div class="alert alert-info py-2 mb-3">
            {{ __('Showing results for') }} "<strong>{{ request('search') }}</strong>" — {{ $articles->total() }} {{ __('found') }}
        </div>
    @endif

    <div class="table-responsive">
        <table id="table-articles" class="table table-hover">
            <thead>
                <tr>
                    <th><x-sortable-th column="article_type" :label="__('Type')" /></th>
                    <th><x-sortable-th column="title" :label="__('Title')" /></th>
                    <th><x-sortable-th column="is_published" :label="__('Published')" /></th>
                    <th><x-sortable-th column="is_public" :label="__('Public')" /></th>
                    <th><x-sortable-th column="expires_at" :label="__('Expires')" /></th>
                    <th><x-sortable-th column="updated_at" :label="__('Updated')" /></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($articles as $a)
                    @php $m = $a->typeMeta(); @endphp
                    <tr class="{{ $a->isExpired() ? 'text-muted' : '' }}" style="cursor:pointer" onclick="if(!event.target.closest('button,form,a'))window.location='{{ route('admin.articles.edit', $a) }}'">
                        <td><span class="badge" style="background:{{ $m['color'] }}">{{ $m['icon'] }} {{ __($m['label']) }}</span></td>
                        <td>{{ $a->title }}@if($a->vote_id) <span class="badge bg-info ms-1">@icon('🗳')️</span>@endif</td>
                        <td>{!! $a->is_published ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
                        <td>{!! $a->is_public ? '<span class="badge bg-info">Public</span>' : '<span class="badge bg-warning text-dark">Members</span>' !!}</td>
                        <td>{{ $a->expires_at?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $a->updated_at->format('d/m/Y H:i') }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.articles.edit', $a) }}" class="btn btn-sm btn-outline-primary">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.articles.destroy', $a) }}" class="d-inline" data-confirm="Delete?" data-confirm-style="danger" data-confirm-btn="{{ __('Confirm') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No articles found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $articles->links() }}
</x-layout>

@include("components.clickable-rows")
