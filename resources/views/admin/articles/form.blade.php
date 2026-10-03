<x-admin-layout :title="$article->exists ? __('Edit Article') : __('New Article')">
    <h4 class="mb-4">{{ $article->exists ? __('Edit Article') : __('New Article') }}</h4>

    <form method="POST" action="{{ $article->exists ? route('admin.articles.update', $article) : route('admin.articles.store') }}" enctype="multipart/form-data">
        @csrf
        @if($article->exists) @method('PUT') @endif

        <div class="row">
            <div class="col-md-8 mb-3">
                <label class="form-label" for="articleTitle">{{ __('Title') }}</label>
                <input type="text" id="articleTitle" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $article->title) }}" required>
                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="articleType">{{ __('Type') }}</label>
                <select name="article_type" id="articleType" class="form-select @error('article_type') is-invalid @enderror" required>
                    @foreach(\App\Models\Article::TYPES as $key => $meta)
                        <option value="{{ $key }}" {{ old('article_type', $article->article_type) === $key ? 'selected' : '' }}>
                            {{ $meta['icon'] }} {{ __($meta['label']) }}
                        </option>
                    @endforeach
                </select>
                @error('article_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="articleBody">{{ __('Body') }}</label>
            <textarea id="articleBody" name="body" class="tinymce">{{ old('body', $article->body) }}</textarea>
            @error('body') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="articleFeaturedImage">{{ __('Featured Image') }}</label>
                <input type="file" id="articleFeaturedImage" name="featured_image" class="form-control @error('featured_image') is-invalid @enderror" accept="image/*">
                @error('featured_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="articleVoteId">{{ __('Attach Vote') }}</label>
                <select name="vote_id" id="articleVoteId" class="form-select">
                    <option value="">{{ __('None') }}</option>
                    @foreach($votes as $v)
                        <option value="{{ $v->id }}" {{ old('vote_id', $article->vote_id) == $v->id ? 'selected' : '' }}>
                            {{ $v->title }} ({{ $v->status }})
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">{{ __('For trip proposals — attach a vote so members can express interest.') }}</small>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-3">
                <div class="form-check">
                    <input type="hidden" name="is_published" value="0">
                    <input type="checkbox" name="is_published" value="1" id="articleIsPublished" class="form-check-input" {{ old('is_published', $article->is_published) ? 'checked' : '' }}>
                    <label class="form-check-label" for="articleIsPublished">{{ __('Published') }}</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input type="hidden" name="is_public" value="0">
                    <input type="checkbox" name="is_public" value="1" id="articleIsPublic" class="form-check-input" {{ old('is_public', $article->is_public ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="articleIsPublic">{{ __('Public (visible without login)') }}</label>
                </div>
            </div>
        </div>

        {{-- Gallery --}}
        <h6 class="mt-3">{{ __('Image Gallery') }}</h6>
        @if($article->exists && $article->images->count())
            <div class="row mb-3">
                @foreach($article->images as $img)
                    <div class="col-md-3 mb-2">
                        <div class="card">
                            <img src="{{ asset('storage/' . $img->file_path) }}" class="card-img-top" style="height:100px;object-fit:cover">
                            <div class="card-body p-2">
                                <small class="text-muted">{{ $img->caption ?? $img->alt_text ?? '—' }} ({{ $img->layout_hint }})</small>
                                <div class="form-check mt-1">
                                    <input type="checkbox" name="delete_images[]" value="{{ $img->id }}" id="deleteImage{{ $img->id }}" class="form-check-input">
                                    <label class="form-check-label small text-danger" for="deleteImage{{ $img->id }}">{{ __('Delete') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        <div id="galleryInputs">
            <div class="row g-2 mb-2 gallery-row">
                {{-- aria-label, not id: the "+ Add image" button below clones this
                     whole row with cloneNode(true), which would duplicate an id --}}
                <div class="col-md-5"><input type="file" name="gallery[]" class="form-control form-control-sm" accept="image/*" aria-label="{{ __('Image') }}"></div>
                <div class="col-md-4"><input type="text" name="gallery_captions[]" class="form-control form-control-sm" placeholder="{{ __('Caption') }}" aria-label="{{ __('Caption') }}"></div>
                <div class="col-md-3">
                    <select name="gallery_layouts[]" class="form-select form-select-sm" aria-label="{{ __('Layout') }}">
                        <option value="full">{{ __('Full width') }}</option>
                        <option value="half">{{ __('Half') }}</option>
                        <option value="third">{{ __('Third') }}</option>
                    </select>
                </div>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="document.getElementById('galleryInputs').appendChild(document.querySelector('.gallery-row').cloneNode(true))">+ {{ __('Add image') }}</button>

        <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        <a href="{{ route('admin.articles.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </form>

    @if($article->exists)
        <form method="POST" action="{{ route('admin.articles.destroy', $article) }}" class="d-inline ms-2" data-confirm="{{ __('Are you sure you want to delete this article?') }}" data-confirm-style="danger" data-confirm-btn="{{ __('Delete') }}">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">{{ __('Delete Article') }}</button>
        </form>
    @endif

    <x-rich-editor />
</x-admin-layout>
