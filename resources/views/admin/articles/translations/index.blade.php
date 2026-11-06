<x-admin-layout :title="__('Manage Translations')">
    <x-breadcrumb :items="[
        __('Articles') => route('admin.articles.index'),
        $article->title => route('admin.articles.edit', $article),
        __('Manage Translations') => '#',
    ]" />

    <h4 class="mb-1">{{ __('Manage Translations') }}</h4>
    <p class="text-muted mb-4">{{ $article->title }}</p>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>{{ __('Language') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                {{-- The French original IS the Article row — edited on the normal form, not here. --}}
                @php $frMeta = config('languages.fr', ['label' => 'French', 'flag' => '🇫🇷']); @endphp
                <tr>
                    <td>{{ $frMeta['flag'] }} {{ __($frMeta['label']) }} <span class="badge bg-secondary">{{ __('Original') }}</span></td>
                    <td class="text-muted small">{{ __('This is the source content every translation is generated from.') }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.articles.edit', $article) }}" class="btn btn-sm btn-outline-primary">{{ __('Edit') }}</a>
                    </td>
                </tr>
                @foreach($locales as $locale)
                    @php
                        $meta = config("languages.{$locale}", ['label' => strtoupper($locale), 'flag' => '🏳️']);
                        $t = $translations->get($locale);
                    @endphp
                    <tr>
                        <td>{{ $meta['flag'] }} {{ __($meta['label']) }}</td>
                        <td>
                            @if(! $t)
                                <span class="badge bg-light text-dark border">{{ __('Not yet translated') }}</span>
                            @elseif($t->flagged_at)
                                <span class="badge bg-danger" title="{{ $t->flag_reason }}">@icon('🚩') {{ __('Flagged for review') }}</span>
                            @elseif(! $t->auto_translated && ! $t->stale)
                                <span class="badge bg-success">@icon('✍️') {{ __('Manually edited') }}</span>
                            @elseif(! $t->auto_translated && $t->stale)
                                <span class="badge bg-warning text-dark">@icon('⚠️') {{ __('Needs attention') }}</span>
                            @elseif($t->stale)
                                <span class="badge bg-warning text-dark">@icon('⏳') {{ __('Outdated — source changed') }}</span>
                            @else
                                <span class="badge bg-info text-dark">@icon('🤖') {{ __('Auto-translated') }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.articles.translations.edit', [$article, $locale]) }}" class="btn btn-sm btn-outline-primary">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('admin.articles.translate', $article) }}">
        @csrf
        <button class="btn btn-outline-secondary btn-sm">@icon('🌐') {{ __('Generate missing/outdated translations') }}</button>
        <small class="text-muted d-block mt-1">{{ __('Manually edited translations are never overwritten by this — only outdated or missing ones are (re)generated.') }}</small>
    </form>
</x-admin-layout>
