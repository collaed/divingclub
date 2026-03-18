@php $d = $target->detail; $isSelf = $viewer->id === $target->id; $isBM = $viewer->isBureauMaster(); @endphp
<form method="POST" action="{{ $isBM && !$isSelf ? route('admin.profile.update.info', $target) : route('profile.update.info') }}">
    @csrf
    <input type="hidden" name="tab" value="info">
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('First Name') }} *</label>
            <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $d?->first_name) }}" required>
            @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Last Name') }} *</label>
            <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $d?->last_name) }}" required>
            @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('Username') }}</label>
            <input type="text" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username', $target->username) }}">
            @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('Nationality') }}</label>
            <input type="text" name="nationality" class="form-control @error('nationality') is-invalid @enderror" value="{{ old('nationality', $d?->nationality) }}">
            @error('nationality') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('Sex') }} *</label>
            <select name="sex" class="form-select @error('sex') is-invalid @enderror" required>
                @foreach(['M' => __('Male'), 'F' => __('Female'), 'X' => __('Other')] as $v => $l)
                    <option value="{{ $v }}" {{ old('sex', $d?->sex) === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>
            @error('sex') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('Phone (Private)') }}</label>
            <input type="text" name="phone_private" class="form-control @error('phone_private') is-invalid @enderror" value="{{ old('phone_private', $d?->phone_private) }}">
            @error('phone_private') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('Phone (Office)') }}</label>
            <input type="text" name="phone_office" class="form-control @error('phone_office') is-invalid @enderror" value="{{ old('phone_office', $d?->phone_office) }}">
            @error('phone_office') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('Phone (Mobile)') }}</label>
            <input type="text" name="phone_mobile" class="form-control @error('phone_mobile') is-invalid @enderror" value="{{ old('phone_mobile', $d?->phone_mobile) }}">
            @error('phone_mobile') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">{{ __('Club Email') }}</label>
        <input type="email" name="cep_email" class="form-control @error('cep_email') is-invalid @enderror" value="{{ old('cep_email', $d?->cep_email) }}">
        @error('cep_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @if($isBM)
        <hr>
        <h6 class="text-muted">{{ __('Bureau Master Only') }}</h6>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">{{ __('Status') }}</label>
                <select name="status_id" class="form-select @error('status_id') is-invalid @enderror">
                    <option value="">—</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s->id }}" {{ old('status_id', $target->status_id) == $s->id ? 'selected' : '' }}>{{ $s->name }} (×{{ $s->fee_multiplier }})</option>
                    @endforeach
                </select>
                @error('status_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">{{ __('Adhesion Year') }}</label>
                <input type="number" name="adhesion_year" class="form-control @error('adhesion_year') is-invalid @enderror" value="{{ old('adhesion_year', $d?->adhesion_year) }}">
                @error('adhesion_year') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">{{ __('Cotisation Years') }}</label>
                <input type="text" name="cotisation_years" class="form-control @error('cotisation_years') is-invalid @enderror" value="{{ old('cotisation_years', $d?->cotisation_years ? implode(', ', $d->cotisation_years) : '') }}" placeholder="2023, 2024, 2025">
                @error('cotisation_years') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input type="hidden" name="bureau_member" value="0">
                    <input type="checkbox" name="bureau_member" value="1" class="form-check-input" {{ old('bureau_member', $d?->bureau_member) ? 'checked' : '' }}>
                    <label class="form-check-label">{{ __('Bureau Member') }}</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check">
                    <input type="hidden" name="active_instructor" value="0">
                    <input type="checkbox" name="active_instructor" value="1" class="form-check-input" {{ old('active_instructor', $d?->active_instructor) ? 'checked' : '' }}>
                    <label class="form-check-label">{{ __('Active Instructor') }}</label>
                </div>
            </div>
        </div>
    @else
        {{-- Show read-only for members --}}
        <div class="row mt-3">
            <div class="col-md-4"><strong>{{ __('Status') }}:</strong> {{ $target->status?->name ?? '—' }}</div>
            <div class="col-md-4"><strong>{{ __('Adhesion Year') }}:</strong> {{ $d?->adhesion_year ?? '—' }}</div>
            <div class="col-md-4"><strong>{{ __('Cotisation Years') }}:</strong> {{ $d?->cotisation_years ? implode(', ', $d->cotisation_years) : '—' }}</div>
        </div>
    @endif

    <button type="submit" class="btn btn-primary mt-3">{{ __('Save') }}</button>
</form>
