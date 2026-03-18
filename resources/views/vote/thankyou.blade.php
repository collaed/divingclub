<x-layout :title="__('Thank You')">
    <div class="row justify-content-center"><div class="col-lg-6 text-center py-5">
        <h4>✓ {{ __('Your vote has been recorded.') }}</h4>
        <p class="text-muted">{{ $vote->title }}</p>
        <p>{{ __('Thank you for participating.') }}</p>
    </div></div>
</x-layout>
