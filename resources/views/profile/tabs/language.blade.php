@php $d = $target->detail; @endphp
<form method="POST" action="{{ route('profile.update.language') }}">
    @csrf
    <input type="hidden" name="tab" value="language">
    <div class="mb-3">
        <label class="form-label">{{ __('Preferred Communication Language') }}</label>
        <select name="preferred_language" class="form-select @error('preferred_language') is-invalid @enderror" style="max-width: 300px;">
            @foreach(['en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch', 'it' => 'Italiano', 'es' => 'Español', 'pt' => 'Português', 'nl' => 'Nederlands', 'pl' => 'Polski', 'ro' => 'Română', 'cs' => 'Čeština', 'el' => 'Ελληνικά', 'lb' => 'Lëtzebuergesch'] as $code => $name)
                <option value="{{ $code }}" {{ old('preferred_language', $d?->preferred_language) === $code ? 'selected' : '' }}>{{ $name }}</option>
            @endforeach
        </select>
        @error('preferred_language') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
</form>
