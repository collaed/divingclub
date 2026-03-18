<x-layout :title="__('Forgot Password')">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card dc-card">
                <div class="card-body p-4">
                    <h4 class="card-title mb-4 text-center">{{ __('Reset Password') }}</h4>
                    <p class="text-muted small">{{ __('Enter your email and we\'ll send a reset link to all your verified addresses.') }}</p>
                    <form method="POST" action="{{ route('password.email') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100">{{ __('Send Reset Link') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <x-auth-validation />
</x-layout>
