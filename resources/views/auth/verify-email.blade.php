<x-layout :title="__('Verify Email')">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card dc-card">
                <div class="card-body p-4 text-center">
                    <h4 class="mb-3">{{ __('Verify Your Email Address') }}</h4>
                    <p>{{ __('A verification link has been sent to your email address. Please check your inbox.') }}</p>
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">{{ __('Resend Verification Email') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layout>
