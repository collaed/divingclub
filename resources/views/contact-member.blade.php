<x-layout :title="__('Contact') . ' — ' . $target->username">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <h4 class="mb-3">@icon('✉️') {{ __('Contact :name', ['name' => $target->username]) }}</h4>
            <p class="text-muted small">{{ __('Your message will be sent via the club. The recipient can reply directly to your email.') }}</p>

            <form method="POST" action="{{ route('contact.member.send', $target) }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">{{ __('Subject') }}</label>
                    <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror" value="{{ old('subject') }}" required maxlength="200">
                    @error('subject') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('Message') }}</label>
                    <textarea name="message" class="form-control @error('message') is-invalid @enderror" rows="6" required maxlength="5000">{{ old('message') }}</textarea>
                    @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <button type="submit" class="btn btn-primary">@icon('📨') {{ __('Send') }}</button>
                <a href="{{ url()->previous() }}" class="btn btn-outline-secondary ms-2">{{ __('Cancel') }}</a>
            </form>
        </div>
    </div>
</x-layout>
