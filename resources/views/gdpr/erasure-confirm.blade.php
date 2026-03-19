<x-layout :title="__('Confirm Data Erasure')">
    <div class="row justify-content-center"><div class="col-lg-6">
        <div class="card dc-card border-danger">
            <div class="card-header bg-danger text-white">⚠️ {{ __('Confirm Data Erasure') }}</div>
            <div class="card-body">
                <p>{{ __('This will permanently:') }}</p>
                <ul>
                    <li>{{ __('Delete all your uploaded documents') }}</li>
                    <li>{{ __('Remove your profile photo') }}</li>
                    <li>{{ __('Anonymize your personal information') }}</li>
                    <li>{{ __('Delete all your email addresses') }}</li>
                    <li>{{ __('Log you out permanently') }}</li>
                </ul>
                <p class="text-danger fw-bold">{{ __('This action cannot be undone.') }}</p>
                <form method="POST" action="{{ route('gdpr.erasure.confirm') }}">
                    @csrf
                    <div class="form-check mb-3">
                        <input type="checkbox" name="confirm" value="1" class="form-check-input @error('confirm') is-invalid @enderror" required>
                        <label class="form-check-label">{{ __('I understand and wish to proceed') }}</label>
                        @error('confirm') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Enter your password to confirm') }}</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button class="btn btn-danger">{{ __('Erase My Data') }}</button>
                    <a href="{{ route('gdpr.consents') }}" class="btn btn-outline-secondary ms-2">{{ __('Cancel') }}</a>
                </form>
            </div>
        </div>
    </div></div>
</x-layout>
