<x-layout :title="__('Free Trial Dive')">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card dc-card mb-4">
                <div class="card-body">
                    <h2>@icon('🐠') {{ __('Free Trial Dive') }}</h2>
                    <p class="lead">{{ __('Curious about scuba diving? Try it for free in our pool!') }}</p>

                    <h4>{{ __('What to expect') }}</h4>
                    <ul>
                        <li>{{ __('A supervised pool session with one of our certified instructors') }}</li>
                        <li>{{ __('All equipment provided (mask, fins, wetsuit, BCD, regulator, tank)') }}</li>
                        <li>{{ __('A brief safety briefing before entering the water') }}</li>
                        <li>{{ __('Your first breaths underwater — in a safe, controlled environment') }}</li>
                        <li>{{ __('Duration: approximately 1 hour (briefing + pool time)') }}</li>
                        <li>{{ __('No prior experience needed — just bring a swimsuit and a towel!') }}</li>
                    </ul>

                    <h4>{{ __('How it works') }}</h4>
                    <ol>
                        <li>{{ __('Fill in the form below to request an appointment') }}</li>
                        <li>{{ __('We will contact you to confirm a date when an instructor is available') }}</li>
                        <li>{{ __('Come to the pool at the agreed time — we handle the rest!') }}</li>
                    </ol>

                    <div class="alert alert-warning">
                        <strong>@icon('⚠️') {{ __('Important health information') }}</strong>
                        <p class="mb-1 small">{{ __('Diving is not recommended if you have any of the following conditions:') }}</p>
                        <ul class="small mb-0">
                            <li>{{ __('Respiratory conditions (asthma, COPD)') }}</li>
                            <li>{{ __('Heart conditions or recent cardiovascular surgery') }}</li>
                            <li>{{ __('Epilepsy or neurological conditions') }}</li>
                            <li>{{ __('Ear or sinus problems') }}</li>
                            <li>{{ __('Pregnancy') }}</li>
                        </ul>
                        <p class="small mt-1 mb-0">{{ __('If in doubt, consult your doctor before diving.') }}</p>
                    </div>

                    <h4>{{ __('After the trial') }}</h4>
                    <p>{{ __('Enjoyed it? You can join the club and start your first certification (P1@icon('★') / 1 Star Diver). See our') }} <a href="{{ url('/article/first-certification') }}">{{ __('First Certification Guide') }}</a> {{ __('for details.') }}</p>
                </div>
            </div>

            {{-- Request form --}}
            <div class="card dc-card">
                <div class="card-header">@icon('📝') {{ __('Request an Appointment') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('trial.store') }}">
                        @csrf
                        <input type="hidden" name="_ts" value="{{ now()->timestamp }}">
                        <div style="display:none"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('First Name') }} *</label>
                                <input type="text" name="first_name" class="form-control" required value="{{ old('first_name') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Last Name') }} *</label>
                                <input type="text" name="last_name" class="form-control" required value="{{ old('last_name') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Email') }} *</label>
                                <input type="email" name="email" class="form-control" required value="{{ old('email') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Phone') }}</label>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Preferred date') }}</label>
                                <input type="date" name="preferred_date" class="form-control" min="{{ date('Y-m-d', strtotime('+1 day')) }}" value="{{ old('preferred_date') }}">
                                <small class="text-muted">{{ __('We will confirm availability with you.') }}</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('Message') }}</label>
                                <textarea name="message" class="form-control" rows="2" placeholder="{{ __('Any questions or special requests?') }}">{{ old('message') }}</textarea>
                            </div>
                        </div>
                        <button class="btn btn-primary mt-3">{{ __('Send Request') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layout>
