<x-admin-layout :title="$federation->acronym . ' — ' . __('Certification Levels')">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('admin.federations.index') }}">{{ __('Federations') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $federation->acronym }}</li>
        </ol>
    </nav>

    <h4>@icon('🎖️') {{ $federation->acronym }} <span class="text-muted fw-normal fs-6">— {{ $federation->full_name }}</span></h4>
    <p class="text-muted small">
        {{ __('Certification levels this federation issues. :rank orders them within a category (low → high). :group links equivalent levels across federations (e.g. cmas_2s) so the app can compare qualifications.', [
            'rank' => __('Rank'), 'group' => __('Equivalence group'),
        ]) }}
    </p>

    @error('level')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror

    <div class="card dc-card">
        <div class="card-body">
            @include('admin.federations._levels', ['federation' => $federation, 'levels' => $levels, 'categories' => $categories])
        </div>
    </div>
</x-admin-layout>
