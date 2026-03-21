<x-layout :title="__('Login')">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card dc-card">
                <div class="card-body p-4">
                    <h4 class="card-title mb-4 text-center">{{ __('Login') }}</h4>

                    <form method="POST" action="{{ route('login') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('Password') }}</label>
                            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required>
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="remember" id="remember" class="form-check-input">
                            <label for="remember" class="form-check-label">{{ __('Remember me') }}</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3">{{ __('Login') }}</button>
                        <div class="text-center">
                            <a href="{{ route('password.request') }}" class="small">{{ __('Forgot your password?') }}</a>
                        </div>
                    </form>

                    @php
                        $providers = collect([
                            'google' => '🔵  Google',
                            'microsoft' => '🟦  Microsoft',
                            'facebook' => '🔷  Facebook',
                            'x' => '⬛  X',
                        ])->filter(fn ($label, $key) => config("services.{$key}.client_id"));
                    @endphp
                    @php $hasEuLogin = !filter_var(parse_url(config('app.url'), PHP_URL_HOST), FILTER_VALIDATE_IP); @endphp
                    @if($providers->isNotEmpty() || $hasEuLogin)
                    <hr>
                    <p class="text-center text-muted small mb-3">{{ __('Or sign in with') }}</p>
                    <div class="d-grid gap-2">
                        @foreach($providers as $provider => $label)
                            <a href="{{ route('auth.social.redirect', $provider) }}" class="btn btn-outline-secondary btn-sm">{{ $label }}</a>
                        @endforeach
                        @if($hasEuLogin)
                            <a href="{{ route('auth.eulogin.redirect') }}" class="btn btn-outline-secondary btn-sm">🇪🇺  EU Login</a>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <x-auth-validation />
</x-layout>
