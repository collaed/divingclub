<x-layout :title="__('Register')">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card dc-card">
                <div class="card-body p-4">
                    <h4 class="card-title mb-4 text-center">{{ __('Register') }}</h4>
                    <form method="POST" action="{{ route('register') }}">
                        @csrf
                        <input type="hidden" name="_ts" value="{{ time() }}">
                        <div style="position:absolute;left:-9999px" aria-hidden="true">
                            <input type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">{{ __('First Name') }}</label>
                                <input type="text" name="first_name" id="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name') }}" required>
                                @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">{{ __('Last Name') }}</label>
                                <input type="text" name="last_name" id="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name') }}" required>
                                @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('Password') }}</label>
                            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required>
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">{{ __('Confirm Password') }}</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
                        </div>

                        <hr class="my-3">
                        <p class="small text-muted mb-2">{{ __('Profile information (helps us prepare your membership)') }}</p>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date_of_birth" class="form-label">{{ __('Date of Birth') }} *</label>
                                <input type="date" name="date_of_birth" id="date_of_birth" class="form-control @error('date_of_birth') is-invalid @enderror" value="{{ old('date_of_birth') }}" required>
                                @error('date_of_birth') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="sex" class="form-label">{{ __('Sex') }} *</label>
                                <select name="sex" id="sex" class="form-select @error('sex') is-invalid @enderror" required>
                                    <option value="">{{ __('— Select —') }}</option>
                                    <option value="M" {{ old('sex') === 'M' ? 'selected' : '' }}>{{ __('Male') }}</option>
                                    <option value="F" {{ old('sex') === 'F' ? 'selected' : '' }}>{{ __('Female') }}</option>
                                    <option value="X" {{ old('sex') === 'X' ? 'selected' : '' }}>{{ __('Other') }}</option>
                                </select>
                                @error('sex') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone_mobile" class="form-label">{{ __('Mobile Phone') }} *</label>
                                <input type="tel" name="phone_mobile" id="phone_mobile" class="form-control @error('phone_mobile') is-invalid @enderror" value="{{ old('phone_mobile') }}" placeholder="+352..." required>
                                @error('phone_mobile') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nationality" class="form-label">{{ __('Nationality') }}</label>
                                @php
                                    $clubTop = ['France', 'Luxembourg', 'Belgium', 'Portugal', 'Italy', 'Germany', 'Romania', 'Spain', 'Greece', 'Poland'];
                                    $eu = ['Austria', 'Bulgaria', 'Croatia', 'Cyprus', 'Czech Republic', 'Denmark', 'Estonia', 'Finland', 'Hungary', 'Ireland', 'Latvia', 'Lithuania', 'Malta', 'Netherlands', 'Slovakia', 'Slovenia', 'Sweden'];
                                    $world = ['Albania', 'Argentina', 'Armenia', 'Australia', 'Azerbaijan', 'Bosnia', 'Brazil', 'Canada', 'China', 'Colombia', 'Georgia', 'Iceland', 'India', 'Iran', 'Israel', 'Japan', 'Kosovo', 'Lebanon', 'Mexico', 'Moldova', 'Montenegro', 'Morocco', 'North Macedonia', 'Norway', 'Philippines', 'Russia', 'Serbia', 'South Korea', 'Switzerland', 'Tunisia', 'Turkey', 'UK', 'Ukraine', 'USA', 'Vietnam'];
                                @endphp
                                <select name="nationality" id="nationality" class="form-select @error('nationality') is-invalid @enderror">
                                    <option value="">{{ __('— Select —') }}</option>
                                    <optgroup label="{{ __('Most common') }}">
                                        @foreach($clubTop as $n)
                                            <option value="{{ $n }}" {{ old('nationality') === $n ? 'selected' : '' }}>{{ $n }}</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="{{ __('EU') }}">
                                        @foreach($eu as $n)
                                            <option value="{{ $n }}" {{ old('nationality') === $n ? 'selected' : '' }}>{{ $n }}</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="{{ __('World') }}">
                                        @foreach($world as $n)
                                            <option value="{{ $n }}" {{ old('nationality') === $n ? 'selected' : '' }}>{{ $n }}</option>
                                        @endforeach
                                    </optgroup>
                                </select>
                                @error('nationality') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address_line1" class="form-label">{{ __('Address') }}</label>
                            <input type="text" name="address_line1" id="address_line1" class="form-control @error('address_line1') is-invalid @enderror" value="{{ old('address_line1') }}" placeholder="{{ __('Street, number') }}">
                            @error('address_line1') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <input type="text" name="postal_code" class="form-control @error('postal_code') is-invalid @enderror" value="{{ old('postal_code') }}" placeholder="{{ __('Postal code') }}">
                            </div>
                            <div class="col-md-8 mb-3">
                                <input type="text" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city') }}" placeholder="{{ __('City') }}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="country" class="form-label">{{ __('Country of residence') }}</label>
                            <select name="country" id="country" class="form-select @error('country') is-invalid @enderror">
                                <option value="Luxembourg" {{ old('country', 'Luxembourg') === 'Luxembourg' ? 'selected' : '' }}>Luxembourg</option>
                                <option value="France" {{ old('country') === 'France' ? 'selected' : '' }}>France</option>
                                <option value="Belgium" {{ old('country') === 'Belgium' ? 'selected' : '' }}>Belgium</option>
                                <option value="Germany" {{ old('country') === 'Germany' ? 'selected' : '' }}>Germany</option>
                            </select>
                            @error('country') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100">{{ __('Register') }}</button>
                    </form>

                    <hr>
                    <p class="text-center text-muted small mb-3">{{ __('Or register with') }}</p>
                    <div class="d-grid gap-2">
                        @foreach(['google' => '🔵  Google', 'microsoft' => '🟦  Microsoft', 'facebook' => '🔷  Facebook', 'x' => '⬛  X', 'amazon' => '🟠  Amazon'] as $provider => $label)
                            @php $authBase = config('services.auth_base_url'); @endphp
                            <a href="{{ $authBase ? $authBase.'/auth/'.$provider.'/redirect' : route('auth.social.redirect', $provider) }}" class="btn btn-outline-secondary btn-sm">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-auth-validation />
</x-layout>
