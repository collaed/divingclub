@php $d = $target->detail; @endphp
<form method="POST" action="{{ route('profile.update.language') }}">
    @csrf
    <input type="hidden" name="tab" value="language">
    <input type="hidden" name="target_user_id" value="{{ $target->id }}">
    <div class="mb-3">
        <label class="form-label">{{ __('Preferred Communication Language') }}</label>
        <select name="preferred_language" class="form-select @error('preferred_language') is-invalid @enderror" style="max-width: 300px;">
            @php
                $clubLangs = ['fr' => '🇫🇷 Français', 'en' => '🇬🇧 English', 'de' => '🇩🇪 Deutsch', 'pt' => '🇵🇹 Português', 'lb' => '🇱🇺 Lëtzebuergesch', 'it' => '🇮🇹 Italiano'];
                $otherLangs = ['nl' => '🇳🇱 Nederlands', 'es' => '🇪🇸 Español', 'pl' => '🇵🇱 Polski', 'ro' => '🇷🇴 Română', 'hu' => '🇭🇺 Magyar', 'el' => '🇬🇷 Ελληνικά', 'et' => '🇪🇪 Eesti', 'sk' => '🇸🇰 Slovenčina', 'fi' => '🇫🇮 Suomi'];
                $currentLang = old('preferred_language', $d?->preferred_language);
            @endphp
            <optgroup label="{{ __('Most common in the club') }}">
                @foreach($clubLangs as $code => $name)
                    <option value="{{ $code }}" {{ $currentLang === $code ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </optgroup>
            <optgroup label="{{ __('Other languages') }}">
                @foreach($otherLangs as $code => $name)
                    <option value="{{ $code }}" {{ $currentLang === $code ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </optgroup>
        </select>
        @error('preferred_language') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label class="form-label">{{ __('Show Icons') }}</label>
        <select name="show_icons" class="form-select" style="max-width: 300px;">
            <option value="" {{ is_null($d?->show_icons) ? 'selected' : '' }}>{{ __('Use club default') }}</option>
            <option value="1" {{ $d?->show_icons === 1 ? 'selected' : '' }}>{{ __('Always show') }}</option>
            <option value="0" {{ $d?->show_icons === 0 ? 'selected' : '' }}>{{ __('Hide') }}</option>
        </select>
        <div class="form-text">{{ __('Controls whether emoji icons appear in menus and headings.') }}</div>
    </div>
    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
</form>
