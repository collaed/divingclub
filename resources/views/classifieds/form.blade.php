<x-layout :title="$article->exists ? __('Edit Classified') : __('Post a Classified')">
    <h4 class="mb-4">{{ $article->exists ? __('Edit Classified') : __('Post a Classified') }}</h4>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="alert alert-info small">
                @icon('🏷️') {{ __('Classifieds are visible to all club members and automatically expire after 30 days. You can extend or renew them from the classifieds page.') }}
            </div>

            <form method="POST" action="{{ $article->exists ? route('classifieds.update', $article) : route('classifieds.store') }}" enctype="multipart/form-data">
                @csrf
                @if($article->exists) @method('PUT') @endif

                <div class="mb-3">
                    <label class="form-label">{{ __('Title') }} *</label>
                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $article->title) }}" required placeholder="{{ __('e.g. Selling Mares BCD, size M') }}">
                    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('Description') }} *</label>
                    <textarea name="body" class="tinymce" rows="6">{{ old('body', $article->body) }}</textarea>
                    @error('body') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('Photo') }}</label>
                    <input type="file" name="featured_image" class="form-control @error('featured_image') is-invalid @enderror" accept="image/*">
                    @error('featured_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary">
                    {{ $article->exists ? __('Save') : __('Post Classified') }}
                </button>
                <a href="{{ route('classifieds.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </form>

            <x-rich-editor />
        </div>
    </div>
</x-layout>
