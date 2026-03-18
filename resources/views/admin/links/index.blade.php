<x-layout :title="__('Links')">
    <h4 class="mb-4">{{ __('Links Management') }}</h4>

    <table class="table table-hover mb-4">
        <thead><tr><th>{{ __('Title') }}</th><th>{{ __('URL') }}</th><th>{{ __('Public') }}</th><th></th></tr></thead>
        <tbody>
            @foreach($links as $link)
                <tr>
                    <td>{{ $link->title }}</td>
                    <td><a href="{{ $link->url }}" target="_blank">{{ Str::limit($link->url, 50) }}</a></td>
                    <td>{!! $link->is_public ? '<span class="badge bg-info">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
                    <td class="text-end">
                        <form method="POST" action="{{ route('admin.links.destroy', $link) }}" class="d-inline" onsubmit="return confirm('Delete?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="card dc-card">
        <div class="card-header">{{ __('Add Link') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.links.store') }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-3">
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" placeholder="{{ __('Title') }}" required>
                        @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <input type="url" name="url" class="form-control @error('url') is-invalid @enderror" placeholder="https://..." required>
                        @error('url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="sort_order" class="form-control @error('sort_order') is-invalid @enderror" placeholder="{{ __('Order') }}" value="0">
                        @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-1">
                        <div class="form-check mt-2">
                            <input type="hidden" name="is_public" value="0">
                            <input type="checkbox" name="is_public" value="1" class="form-check-input" checked>
                            <label class="form-check-label">{{ __('Public') }}</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary">{{ __('Add') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-layout>
