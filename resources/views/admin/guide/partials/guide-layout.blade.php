{{-- Guide page wrapper: @include with $guideContent variable --}}
<x-layout :title="$title . ' — Admin Guide'">
<div class="row">
    <div class="col-lg-3 mb-4">
        <div class="card dc-card">
            <div class="card-header"><a href="{{ route('admin.guide.index') }}" class="text-decoration-none">📖 Admin Guide</a></div>
            <div class="list-group list-group-flush">
                @foreach($sections as $slug => $sTitle)
                    <a href="{{ route('admin.guide.show', $slug) }}" class="list-group-item list-group-item-action {{ $current === $slug ? 'active' : '' }}">{{ $sTitle }}</a>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="card dc-card">
            <div class="card-body">
                <h4 class="mb-4">{{ $title }}</h4>
                @yield('content')
            </div>
            <div class="card-footer d-flex justify-content-between">
                @if($prev ?? null)<a href="{{ route('admin.guide.show', $prev['slug']) }}">← {{ $prev['title'] }}</a>@else<span></span>@endif
                @if($next ?? null)<a href="{{ route('admin.guide.show', $next['slug']) }}">{{ $next['title'] }} →</a>@else<span></span>@endif
            </div>
        </div>
    </div>
</div>
</x-layout>
