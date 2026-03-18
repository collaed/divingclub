<x-layout :title="__('Vote Closed')">
    <div class="row justify-content-center"><div class="col-lg-6 text-center py-5">
        <h4>{{ $vote->title }}</h4>
        <p class="text-muted">{{ __('This vote is no longer open.') }}</p>
    </div></div>
</x-layout>
