<x-layout :title="__('Account not found')">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card dc-card">
                <div class="card-body p-4">
                    <h4 class="text-center mb-3">{{ $provider }} {{ __('Login') }}</h4>

                    <div class="alert alert-warning">
                        <strong>{{ __('Email not recognised') }}</strong><br>
                        {{ __('The email address :email from your :provider account is not in our member database.', ['email' => $email, 'provider' => $provider]) }}
                    </div>

                    <p class="text-muted">{{ __('This could happen if:') }}</p>
                    <ul class="text-muted small">
                        <li>{{ __('You registered with a different email address — try logging in with your password and add this email to your profile.') }}</li>
                        <li>{{ __('You are new to the club — you can create a new account below (subject to bureau approval).') }}</li>
                    </ul>

                    <hr>

                    {{-- Option 1: I already have an account with a different email --}}
                    <h5 class="mb-3">@icon('🔗') {{ __('I already have an account') }}</h5>
                    <p class="small text-muted">{{ __('Log in with your existing email and password to link your :provider identity.', ['provider' => $provider]) }}</p>

                    <form method="POST" action="{{ route('auth.social.link-existing') }}">
                        @csrf
                        <div class="mb-2">
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="{{ __('Your existing club email') }}" value="{{ old('email') }}" required>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <input type="password" name="password" class="form-control" placeholder="{{ __('Password') }}" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">{{ __('Link to my existing account') }}</button>
                    </form>

                    <hr>

                    {{-- Option 2: I'm new to the club --}}
                    <h5 class="mb-3">@icon('🆕') {{ __("I'm new to the club") }}</h5>
                    <p class="small text-muted">{{ __('Create a new account with :email. Your account will require bureau approval before you can access member features.', ['email' => $email]) }}</p>

                    <form method="POST" action="{{ route('auth.social.create-new') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary w-100">{{ __('Create new account (pending approval)') }}</button>
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
