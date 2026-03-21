@php $d = $target->detail; $isSelf = $viewer->id === $target->id; @endphp
<form method="POST" action="{{ $viewer->isBureauMaster() && !$isSelf ? route('admin.profile.update.private', $target) : route('profile.update.private') }}">
    @csrf
    <input type="hidden" name="tab" value="private">
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Date of Birth') }}</label>
            <input type="date" name="date_of_birth" class="form-control @error('date_of_birth') is-invalid @enderror" value="{{ old('date_of_birth', $d?->date_of_birth?->format('Y-m-d')) }}">
            @error('date_of_birth') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Place of Birth') }}</label>
            <input type="text" name="place_of_birth" class="form-control @error('place_of_birth') is-invalid @enderror" value="{{ old('place_of_birth', $d?->place_of_birth) }}">
            @error('place_of_birth') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Address Line 1') }}</label>
            <input type="text" name="address_line1" class="form-control @error('address_line1') is-invalid @enderror" value="{{ old('address_line1', $d?->address_line1) }}">
            @error('address_line1') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Address Line 2') }}</label>
            <input type="text" name="address_line2" class="form-control @error('address_line2') is-invalid @enderror" value="{{ old('address_line2', $d?->address_line2) }}">
            @error('address_line2') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('City') }}</label>
            <input type="text" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $d?->city) }}">
            @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('Postal Code') }}</label>
            <input type="text" name="postal_code" class="form-control @error('postal_code') is-invalid @enderror" value="{{ old('postal_code', $d?->postal_code) }}">
            @error('postal_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('Country') }}</label>
            <input type="text" name="country" class="form-control @error('country') is-invalid @enderror" value="{{ old('country', $d?->country) }}">
            @error('country') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('IBAN') }} <small class="text-muted">({{ __('for faster payment reconciliation') }})</small></label>
            <input type="text" name="iban" data-mask="iban" class="form-control @error('iban') is-invalid @enderror" value="{{ old('iban', $d?->iban) }}" placeholder="LU00 0000 0000 0000 0000">
            @error('iban') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <hr>
    <h6>{{ __('Emergency Contact') }}</h6>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('Name') }}</label>
            <input type="text" name="emergency_contact_name" class="form-control @error('emergency_contact_name') is-invalid @enderror" value="{{ old('emergency_contact_name', $d?->emergency_contact_name) }}">
            @error('emergency_contact_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('Phone') }}</label>
            <input type="tel" name="emergency_contact_phone" class="form-control @error('emergency_contact_phone') is-invalid @enderror" value="{{ old('emergency_contact_phone', $d?->emergency_contact_phone) }}" placeholder="+352 621 123 456">
            @error('emergency_contact_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('Relationship') }}</label>
            <input type="text" name="emergency_contact_relationship" class="form-control @error('emergency_contact_relationship') is-invalid @enderror" value="{{ old('emergency_contact_relationship', $d?->emergency_contact_relationship) }}">
            @error('emergency_contact_relationship') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">{{ __('Brevet Date') }}</label>
        <input type="date" name="brevet_date" class="form-control @error('brevet_date') is-invalid @enderror" value="{{ old('brevet_date', $d?->brevet_date?->format('Y-m-d')) }}">
        @error('brevet_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
</form>
