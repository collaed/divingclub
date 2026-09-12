<x-admin-layout :title="__('Add Member')">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h4 class="mb-0">{{ __('Add Member') }}</h4>
        <a href="{{ route('admin.members.index') }}" class="btn btn-outline-secondary btn-sm">← {{ __('Back to member list') }}</a>
    </div>

    <p class="text-muted small">{{ __('Members are expected to register themselves. Use this only for exceptions — no email, joined on paper, etc. The member is created active and gets a link to set their own password; you can fill in the rest of their profile afterwards.') }}</p>

    <div class="card dc-card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.members.store') }}" class="row g-3">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">{{ __('First name') }}</label>
                    <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name') }}" required>
                    @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Last name') }}</label>
                    <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name') }}" required>
                    @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Email') }}</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Status') }}</label>
                    <select name="status_id" class="form-select @error('status_id') is-invalid @enderror">
                        @foreach($statuses as $s)
                            <option value="{{ $s->id }}" {{ old('status_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>
                    @error('status_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Date of birth') }}</label>
                    <input type="date" name="date_of_birth" class="form-control @error('date_of_birth') is-invalid @enderror" value="{{ old('date_of_birth') }}">
                    @error('date_of_birth') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Mobile phone') }}</label>
                    <input type="text" name="phone_mobile" class="form-control @error('phone_mobile') is-invalid @enderror" value="{{ old('phone_mobile') }}">
                    @error('phone_mobile') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Nationality') }}</label>
                    <x-country-select name="nationality" :value="old('nationality')" />
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">@icon('➕') {{ __('Create member') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
