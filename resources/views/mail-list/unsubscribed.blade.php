<x-layout :title="__('Unsubscribed')">
    <div class="row justify-content-center"><div class="col-lg-6 text-center py-5">
        <h4>@icon('✓') {{ __('You have been unsubscribed.') }}</h4>
        <p class="text-muted">{{ $email }}</p>
        <p>{{ __('You will no longer receive mail sent to the aliases you were part of.') }}</p>
    </div></div>
</x-layout>
