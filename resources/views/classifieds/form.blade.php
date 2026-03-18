<x-layout :title="$article->exists ? __('Edit Classified') : __('Post a Classified')">
    <h4 class="mb-4">{{ $article->exists ? __('Edit Classified') : __('Post a Classified') }}</h4>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="alert alert-info small">
                🏷️ {{ __('Classifieds are visible to all club members and automatically expire after 30 days. You can extend or renew them from the classifieds page.') }}
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
                    <div id="editor">{!! old('body', $article->body) !!}</div>
                    <input type="hidden" name="body" id="bodyInput">
                    @error('body') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('Photo') }}</label>
                    <input type="file" name="featured_image" class="form-control @error('featured_image') is-invalid @enderror" accept="image/*">
                    @error('featured_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary" onclick="document.getElementById('bodyInput').value=document.getElementById('editor').querySelector('.ql-editor').innerHTML">
                    {{ $article->exists ? __('Save') : __('Post Classified') }}
                </button>
                <a href="{{ route('classifieds.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </form>

            <link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
            <style>#editor { min-height: 150px; background: #fff; }</style>
            <script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
            <script>new Quill('#editor', { theme: 'snow', modules: { toolbar: [
                ['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link', 'image'], ['clean']
            ]}});</script>
        </div>
    </div>
</x-layout>
