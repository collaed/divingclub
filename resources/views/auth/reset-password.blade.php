<x-layout :title="__('Reset Password')">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card dc-card">
                <div class="card-body p-4">
                    <h4 class="card-title mb-4 text-center">{{ __('Set New Password') }}</h4>
                    <form method="POST" action="{{ route('password.update') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $email ?? '') }}" required>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('New Password') }}</label>
                            <input type="password" name="password" id="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror" required>
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">{{ __('Confirm Password') }}</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" class="form-control @error('password_confirmation') is-invalid @enderror" required>
                            @error('password_confirmation') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="show-password">
                            <label class="form-check-label small text-muted" for="show-password">{{ __('Show password') }}</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">{{ __('Reset Password') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <x-auth-validation />

    @push('scripts')
        <script>
            document.getElementById('show-password')?.addEventListener('change', function () {
                const type = this.checked ? 'text' : 'password';
                document.getElementById('password').type = type;
                document.getElementById('password_confirmation').type = type;
            });
        </script>
    @endpush
</x-layout>
