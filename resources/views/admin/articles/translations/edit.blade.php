@php
    $meta = config("languages.{$locale}", ['label' => strtoupper($locale), 'flag' => '🏳️']);
@endphp
<x-admin-layout :title="__('Edit Translation').' — '.__($meta['label'])">
    <x-breadcrumb :items="[
        __('Articles') => route('admin.articles.index'),
        $article->title => route('admin.articles.edit', $article),
        __('Manage Translations') => route('admin.articles.translations', $article),
        __($meta['label']) => '#',
    ]" />

    <h4 class="mb-4">{{ $meta['flag'] }} {{ __($meta['label']) }} {{ __('Translation') }}</h4>

    @if($translation)
        @if($translation->flagged_at)
            <div class="alert alert-danger">@icon('🚩') {{ __('Flagged for review') }}: {{ $translation->flag_reason }}</div>
        @elseif(! $translation->auto_translated && $translation->stale)
            <div class="alert alert-warning">@icon('⚠️') {{ __('Automatic translation failed for this language — the text below is the untranslated French original. Edit it manually or try regenerating.') }}</div>
        @elseif($translation->stale)
            <div class="alert alert-warning">@icon('⏳') {{ __('The French original was edited since this translation was generated — it may be outdated.') }}</div>
        @elseif(! $translation->auto_translated)
            <div class="alert alert-success">@icon('✍️') {{ __('This translation was manually edited. It will not be overwritten by automatic translation.') }}</div>
        @else
            <div class="alert alert-info">@icon('🤖') {{ __('Auto-translated. Editing and saving below marks it as manually curated.') }}</div>
        @endif
    @else
        <div class="alert alert-secondary">{{ __('No translation yet for this language — the French original is shown as a starting point. Save to create one, or generate it automatically.') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-6 mb-4">
            <h6 class="text-muted">🇫🇷 {{ __('French original') }} <span class="badge bg-secondary">{{ __('Reference') }}</span></h6>
            <div class="card dc-card">
                <div class="card-body">
                    <p class="fw-bold">{{ $article->title }}</p>
                    <div class="small">{!! $article->renderedBody() !!}</div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <h6 class="text-muted">{{ $meta['flag'] }} {{ __($meta['label']) }}</h6>
            <form method="POST" action="{{ route('admin.articles.translations.update', [$article, $locale]) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="translation-title" class="form-label">{{ __('Title') }}</label>
                    <input type="text" id="translation-title" name="title" class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title', $translation->title ?? $article->title) }}" required>
                    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="translation-body" class="form-label">{{ __('Body') }}</label>
                    <textarea id="translation-body" name="body" class="tinymce">{{ old('body', $translation->body ?? $article->body) }}</textarea>
                    @error('body') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary">{{ __('Save Translation') }}</button>
                <a href="{{ route('admin.articles.translations', $article) }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
            </form>

            <form method="POST" action="{{ route('admin.articles.translations.regenerate', [$article, $locale]) }}" class="mt-2"
                  data-confirm="{{ __('Regenerate this translation with AI? Any manual edits to it will be discarded.') }}"
                  data-confirm-style="danger" data-confirm-btn="{{ __('Regenerate') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">@icon('🔁') {{ __('Regenerate with AI') }}</button>
            </form>
        </div>
    </div>

    <x-rich-editor :upload-url="route('admin.articles.upload-image', $article)" />
</x-admin-layout>
