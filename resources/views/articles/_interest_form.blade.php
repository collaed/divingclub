{{-- Inline "leave your details" form for articles: drops a trial_requests row
     the bureau sees at /admin/trial-requests. Rendered where an article body
     contains the [[interest-form]] marker. --}}
<div class="card dc-card my-4 border-primary">
    <div class="card-body">
        <h5 class="mb-1">@icon('✉️') {{ __('Leave us your details') }}</h5>
        <p class="text-muted small mb-3">{{ __('No need to write an email — fill this in and the bureau will get back to you.') }}</p>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ route('trial.store') }}">
            @csrf
            <input type="hidden" name="_ts" value="{{ now()->timestamp }}">
            <input type="hidden" name="source" value="{{ __('Article') }}: {{ $slug }}">
            <div style="display:none"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

            <div class="row g-2">
                <div class="col-sm-6">
                    <label class="form-label small mb-1">{{ __('First name') }} *</label>
                    <input type="text" name="first_name" class="form-control form-control-sm" required value="{{ old('first_name') }}">
                </div>
                <div class="col-sm-6">
                    <label class="form-label small mb-1">{{ __('Last name') }} *</label>
                    <input type="text" name="last_name" class="form-control form-control-sm" required value="{{ old('last_name') }}">
                </div>
                <div class="col-sm-6">
                    <label class="form-label small mb-1">{{ __('Email') }} *</label>
                    <input type="email" name="email" class="form-control form-control-sm" required value="{{ old('email') }}">
                </div>
                <div class="col-sm-6">
                    <label class="form-label small mb-1">{{ __('Phone') }}</label>
                    <input type="text" name="phone" class="form-control form-control-sm" value="{{ old('phone') }}">
                </div>
                <div class="col-12">
                    <label class="form-label small mb-1">{{ __('Message') }}</label>
                    <textarea name="message" class="form-control form-control-sm" rows="2" placeholder="{{ __('Any questions?') }}">{{ old('message') }}</textarea>
                </div>
            </div>
            <button class="btn btn-primary btn-sm mt-3">{{ __('Send') }}</button>
        </form>
    </div>
</div>
