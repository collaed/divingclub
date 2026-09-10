<x-layout :title="__('Login')">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card dc-card">
                <div class="card-body p-4">
                    <h4 class="card-title mb-4 text-center">{{ __('Login') }}</h4>

                    <form method="POST" action="{{ route('login') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('Email or username') }}</label>
                            <input type="text" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus autocapitalize="none" autocomplete="username" spellcheck="false">
                            <div class="form-text">{{ __('Your club username, or any of your email addresses.') }}</div>
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
                            <a href="{{ route('password.request') }}" id="forgotLink" class="small">{{ __('Forgot your password?') }}</a>
                        </div>
                    </form>

                    {{-- "Forgot password?" sends a reset link straight to the address
                         typed above when it looks like an email; otherwise it opens
                         the dedicated page. --}}
                    <form method="POST" action="{{ route('password.email') }}" id="forgotForm" class="d-none">
                        @csrf
                        <input type="hidden" name="email" id="forgotEmail">
                    </form>
                    <script>
                        document.getElementById('forgotLink').addEventListener('click', function (e) {
                            var v = (document.getElementById('email').value || '').trim();
                            if (v.indexOf('@') > 0) {
                                e.preventDefault();
                                document.getElementById('forgotEmail').value = v;
                                document.getElementById('forgotForm').submit();
                            }
                        });
                    </script>

                    @php
                        $providers = collect([
                            'google' => '🔵  Google',
                            'microsoft' => '🟦  Microsoft',
                            'facebook' => '🔷  Facebook',
                            'x' => '⬛  X',
                        ])->filter(fn ($label, $key) => config("services.{$key}.client_id"));
                    @endphp
                    @if($providers->isNotEmpty())
                    <hr>
                    <p class="text-center text-muted small mb-3">{{ __('Or sign in with') }}</p>
                    <div class="d-grid gap-2">
                        @foreach($providers as $provider => $label)
                            @php $authBase = config('services.auth_base_url'); @endphp
                            <a href="{{ $authBase ? $authBase.'/auth/'.$provider.'/redirect' : route('auth.social.redirect', $provider) }}" class="btn btn-outline-secondary btn-sm">{{ $label }}</a>
                        @endforeach
                    </div>
                    @else
                    <hr>
                    <div class="d-grid gap-2">
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <x-auth-validation />
</x-layout>
