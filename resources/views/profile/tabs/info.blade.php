@php $d = $target->detail; $isSelf = $viewer->id === $target->id; $isBM = $viewer->can('manage members'); $canEdit = $canEdit ?? ($isSelf || $isBM); @endphp
@if($canEdit)
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
            @php
                $clubTop = ['France', 'Luxembourg', 'Belgium', 'Portugal', 'Italy', 'Germany', 'Romania', 'Spain', 'Greece', 'Poland'];
                $eu = ['Austria', 'Bulgaria', 'Croatia', 'Cyprus', 'Czech Republic', 'Denmark', 'Estonia', 'Finland', 'Hungary', 'Ireland', 'Latvia', 'Lithuania', 'Malta', 'Netherlands', 'Slovakia', 'Slovenia', 'Sweden'];
                $world = ['Albania', 'Argentina', 'Armenia', 'Australia', 'Azerbaijan', 'Bosnia', 'Brazil', 'Canada', 'China', 'Colombia', 'Georgia', 'Iceland', 'India', 'Iran', 'Israel', 'Japan', 'Kosovo', 'Lebanon', 'Mexico', 'Moldova', 'Montenegro', 'Morocco', 'North Macedonia', 'Norway', 'Philippines', 'Russia', 'Serbia', 'South Korea', 'Switzerland', 'Tunisia', 'Turkey', 'UK', 'Ukraine', 'USA', 'Vietnam'];
                $currentVal = old('nationality', $d?->nationality);
            @endphp
            <select name="nationality" class="form-select @error('nationality') is-invalid @enderror">
                <option value="">{{ __('— Select —') }}</option>
                <optgroup label="{{ __('Most common') }}">
                    @foreach($clubTop as $n)
                        <option value="{{ $n }}" {{ $currentVal === $n ? 'selected' : '' }}>{{ $n }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="{{ __('EU') }}">
                    @foreach($eu as $n)
                        <option value="{{ $n }}" {{ $currentVal === $n ? 'selected' : '' }}>{{ $n }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="{{ __('World') }}">
                    @foreach($world as $n)
                        <option value="{{ $n }}" {{ $currentVal === $n ? 'selected' : '' }}>{{ $n }}</option>
                    @endforeach
                </optgroup>
                @if($currentVal && !in_array($currentVal, array_merge($clubTop, $eu, $world)))
                    <option value="{{ $currentVal }}" selected>{{ $currentVal }}</option>
                @endif
            </select>
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
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Phone (Mobile)') }}</label>
            <input type="tel" name="phone_mobile" class="form-control @error('phone_mobile') is-invalid @enderror" value="{{ old('phone_mobile', $d?->phone_mobile) }}" placeholder="+352 621 123 456">
            @error('phone_mobile') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">{{ __('Club Email') }}</label>
            <input type="email" name="club_email" class="form-control @error('club_email') is-invalid @enderror" value="{{ old('club_email', $d?->club_email) }}">
            @error('club_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    @if($viewer->id === $target->id || $isBM)
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('Membership Status') }}</label>
            <select name="status_id" class="form-select @error('status_id') is-invalid @enderror">
                @foreach($statuses as $s)
                    <option value="{{ $s->id }}" {{ old('status_id', $target->status_id) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
            <div class="form-text">{{ __('Your fee will be adjusted accordingly at next renewal.') }}</div>
            @error('status_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    @endif

    @if($isBM)
        <hr>
        <h6 class="text-muted">{{ __('Bureau Master Only') }}</h6>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">{{ __('Adhesion Year') }}</label>
                <input type="number" name="adhesion_year" class="form-control @error('adhesion_year') is-invalid @enderror" value="{{ old('adhesion_year', $d?->adhesion_year) }}">
                @error('adhesion_year') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-8 mb-3">
                <label class="form-label">{{ __('Cotisation Years') }}</label>
                @php
                    $startY = max((int)($d?->adhesion_year ?? date('Y') - 5), date('Y') - 10);
                    $endY = (int)date('Y') + 1;
                    $paid = $d?->cotisation_years ?? [];
                @endphp
                <div class="d-flex flex-wrap gap-2">
                    @for($y = $endY; $y >= $startY; $y--)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="cotisation_years[]" value="{{ $y }}" id="cot_{{ $y }}" {{ in_array((string)$y, $paid) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="cot_{{ $y }}">{{ $y }}</label>
                        </div>
                    @endfor
                </div>
                @error('cotisation_years') <div class="text-danger small">{{ $message }}</div> @enderror
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
            <div class="col-md-4"><strong>{{ __('Cotisation Years') }}:</strong> {{ $d?->cotisation_years ? implode(', ', $d->cotisation_years) : '—' }}
                @if($d?->cotisation_years && !in_array((string)date('Y'), $d->cotisation_years))
                    <span class="badge bg-danger ms-1">{{ date('Y') }} ✗</span>
                @endif
            </div>
        </div>
    @endif

    <button type="submit" class="btn btn-primary mt-3">{{ __('Save') }}</button>
</form>
@else
{{-- Read-only Batch 2 (Deck) view for regular members --}}
<table class="table table-sm">
    <tr><th style="width:180px">{{ __('First Name') }}</th><td>{{ $d?->first_name ?? '—' }}</td></tr>
    <tr><th>{{ __('Last Name') }}</th><td>{{ $d?->last_name ?? '—' }}</td></tr>
    <tr><th>{{ __('Nationality') }}</th><td>{{ $d?->nationality ?? '—' }}</td></tr>
    <tr><th>{{ __('Sex') }}</th><td>{{ $d?->sex ?? '—' }}</td></tr>
    <tr><th>{{ __('Status') }}</th><td>{{ $target->status?->name ?? '—' }}</td></tr>
    <tr><th>{{ __('Member Since') }}</th><td>{{ $d?->adhesion_year ?? '—' }}</td></tr>
</table>
@endif
