<x-layout :title="__('Link or Register')">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card dc-card">
                <div class="card-body p-4">
                    <h4 class="text-center mb-3">{{ $provider }} {{ __('Login') }}</h4>
                    <p class="text-center text-muted">
                        {{ __('Welcome, :name!', ['name' => $name ?: $email]) }}<br>
                        {{ __('We found no account matching :email.', ['email' => $email]) }}
                    </p>

                    <hr>

                    {{-- Option 1: I already have an account --}}
                    <h5 class="mb-3">🔗 {{ __('I already have an account') }}</h5>
                    <p class="small text-muted">{{ __('Log in with your existing email and password to link your :provider identity.', ['provider' => $provider]) }}</p>

                    <form method="POST" action="{{ route('auth.social.link-existing') }}">
                        @csrf
                        <div class="mb-2">
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="{{ __('Your existing email') }}" value="{{ old('email') }}" required>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <input type="password" name="password" class="form-control" placeholder="{{ __('Password') }}" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">{{ __('Link to my existing account') }}</button>
                    </form>

                    <hr>

                    {{-- Option 2: I'm new --}}
                    <h5 class="mb-3">🆕 {{ __("I'm a new member") }}</h5>
                    <p class="small text-muted">{{ __('Create a new account with :email. You can complete your profile after.', ['email' => $email]) }}</p>

                    <form method="POST" action="{{ route('auth.social.create-new') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-success w-100">{{ __('Create new account') }}</button>
                    </form>

                    <hr>
                    <div class="text-center">
                        <a href="{{ route('login') }}" class="small text-muted">{{ __('Cancel and go back to login') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layout>
