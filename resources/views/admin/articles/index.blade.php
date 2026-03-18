<x-layout :title="__('Articles')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">{{ __('Articles') }}</h4>
        <a href="{{ route('admin.articles.create') }}" class="btn btn-primary">{{ __('New Article') }}</a>
    </div>

    <div class="mb-3 d-flex flex-wrap gap-1">
        <a href="{{ route('admin.articles.index') }}" class="btn btn-sm {{ !request('type') ? 'btn-primary' : 'btn-outline-secondary' }}">{{ __('All') }}</a>
        @foreach(\App\Models\Article::TYPES as $key => $meta)
            <a href="{{ route('admin.articles.index', ['type' => $key]) }}" class="btn btn-sm {{ request('type') === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $meta['icon'] }} {{ __($meta['label']) }}
            </a>
        @endforeach
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Title') }}</th><th>{{ __('Published') }}</th><th>{{ __('Public') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Updated') }}</th><th></th></tr></thead>
            <tbody>
                @foreach($articles as $a)
                    @php $m = $a->typeMeta(); @endphp
                    <tr class="{{ $a->isExpired() ? 'text-muted' : '' }}">
                        <td><span class="badge" style="background:{{ $m['color'] }}">{{ $m['icon'] }} {{ __($m['label']) }}</span></td>
                        <td>{{ $a->title }}@if($a->vote_id) <span class="badge bg-info ms-1">🗳️</span>@endif</td>
                        <td>{!! $a->is_published ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
                        <td>{!! $a->is_public ? '<span class="badge bg-info">Public</span>' : '<span class="badge bg-warning text-dark">Members</span>' !!}</td>
                        <td>{{ $a->expires_at?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $a->updated_at->format('d/m/Y H:i') }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.articles.edit', $a) }}" class="btn btn-sm btn-outline-primary">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.articles.destroy', $a) }}" class="d-inline" onsubmit="return confirm('Delete?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $articles->links() }}
</x-layout>
