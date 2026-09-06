<x-layout :title="__('Membership Dues Calculator')">
    <div class="row">
        <div class="col-12">
            <div class="card dc-card">
                <div class="card-header">{{ __('Membership Dues Calculator') }} {{ $year }}</div>
                <div class="card-body" data-dues-body>
                    <p class="text-muted">{{ __('Calculate your membership dues and get the payment communication string for your bank transfer.') }}</p>

                    @if($isGuest)
                        <div class="alert alert-info py-2">
                            <a href="{{ route('login') }}">{{ __('Log in') }}</a> {{ __('to pre-fill your details and commit to a payment.') }}
                        </div>
                    @elseif($unclassified)
                        <div class="alert alert-warning py-2">
                            {{ __('Your membership category has not been assigned by the bureau yet. You can preview all options and commit to a payment — the bureau will confirm your final status.') }}
                        </div>
                    @endif

                    @if($taperPct < 100)
                        <div class="alert alert-success py-2">
                            {{ __('Reduced season rate in effect: :pct% of the full membership.', ['pct' => $taperPct]) }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('dues.calculate') }}" id="dues-form" data-dues-form>
                        @csrf
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label" for="dc-season">{{ __('Season Year') }}</label>
                                <input type="text" id="dc-season" name="season_year" class="form-control @error('season_year') is-invalid @enderror" value="{{ $year }}" required>
                                @error('season_year') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="dc-last">{{ __('Last Name') }}</label>
                                <input type="text" id="dc-last" name="last_name" class="form-control" value="{{ $lastName ?? (auth()->user()?->detail?->last_name ?? '') }}" required placeholder="DUPONT">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="dc-first">{{ __('First Name') }}</label>
                                <input type="text" id="dc-first" name="first_name" class="form-control" value="{{ $firstName ?? (auth()->user()?->detail?->first_name ?? '') }}" required placeholder="Marie">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="dc-dob">{{ __('Date of Birth') }}</label>
                                <input type="date" id="dc-dob" name="date_of_birth" class="form-control @error('date_of_birth') is-invalid @enderror"
                                       value="{{ old('date_of_birth', $memberDob ?? '') }}" data-dues-input>
                                <div class="form-text">{{ __('Used to determine the federation licence and FLASSA at the season start.') }}</div>
                                @error('date_of_birth') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Group 1 — COTISATION CEP (mandatory, user-chosen) --}}
                        <fieldset class="dc-group mb-3">
                            <legend class="form-label h6">{{ __('Cotisation CEP') }} {{ $year }}</legend>
                            <div class="col-md-6">
                                <label class="visually-hidden" for="dc-status">{{ __('Member Status') }}</label>
                                <select id="dc-status" name="status_id" class="form-select @error('status_id') is-invalid @enderror" required data-dues-input>
                                    <option value="">{{ __('Select your status...') }}</option>
                                    @foreach($statuses as $s)
                                        <option value="{{ $s->id }}" {{ ($statusId ?? '') == $s->id ? 'selected' : '' }}>
                                            {{ $s->name }}@isset($fees[$s->id]) — €{{ number_format($fees[$s->id]->amount, 2) }} @endisset
                                        </option>
                                    @endforeach
                                </select>
                                @if(!$isGuest && !$unclassified)
                                    <div class="form-text">{{ __('Options are limited to your assigned membership category.') }}</div>
                                @endif
                                @error('status_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </fieldset>

                        {{-- Group 2 & 4 — LICENCE FFESSM + FLASSA (derived, read-only) --}}
                        <fieldset class="dc-group mb-3">
                            <legend class="form-label h6">{{ __('Federation Licence') }} (FFESSM)</legend>
                            <div class="dc-derived" role="status" aria-live="polite" data-dues-licence data-flassa-state="{{ $flassaState ?? 'not_applicable' }}" data-ffessm-licence="{{ $derivedFfessm ?? '' }}">
                                @isset($derivedFfessm)
                                    @php $lic = $ffessmLicences[$derivedFfessm] ?? null; @endphp
                                    <div class="d-flex justify-content-between">
                                        <span>{{ $lic?->name ?? $derivedFfessm }}</span>
                                        <span>€{{ number_format((float) ($components[$derivedFfessm] ?? 0), 2) }}</span>
                                    </div>
                                    @if(($flassaState ?? 'not_applicable') !== 'not_applicable')
                                        <div class="d-flex justify-content-between {{ $flassaState === 'included_free' ? 'text-muted' : '' }}">
                                            <span>
                                                {{ __('FLASSA licence') }}
                                                @if($flassaState === 'included_free') <em>({{ __('included') }})</em> @endif
                                            </span>
                                            <span>€{{ number_format((float) ($components['flassa'] ?? 0), 2) }}</span>
                                        </div>
                                    @endif
                                @else
                                    <p class="text-muted mb-0">{{ __('The federation licence is determined automatically from your status and age.') }}</p>
                                @endisset
                            </div>
                        </fieldset>

                        {{-- Group 3 — ASSURANCE Individuelle (optional, gated by licence) --}}
                        @php $assuranceForced = ($flassaState ?? null) === 'not_applicable' && isset($derivedFfessm); @endphp
                        <fieldset class="dc-group mb-3" @if($assuranceForced) aria-describedby="dc-assurance-note" @endif>
                            <legend class="form-label h6">{{ __('Assurance Individuelle') }}</legend>
                            @if($assuranceForced)
                                <p id="dc-assurance-note" class="text-muted small">{{ __('Personal insurance requires a federation licence and is not available for this status.') }}</p>
                            @endif
                            @foreach($optionals as $opt)
                                <div class="form-check">
                                    <input type="checkbox" name="optionals[]" value="{{ $opt->slug }}" class="form-check-input"
                                           id="opt_{{ $opt->slug }}" data-dues-input
                                           {{ in_array($opt->slug, $selectedOptionals ?? []) ? 'checked' : '' }}
                                           @if($assuranceForced) disabled aria-disabled="true" @endif>
                                    <label class="form-check-label" for="opt_{{ $opt->slug }}">
                                        {{ $opt->name }} — €{{ number_format($opt->amount, 2) }}
                                        @if($opt->description) <small class="text-muted">({{ $opt->description }})</small> @endif
                                    </label>
                                </div>
                            @endforeach
                        </fieldset>

                        <button type="submit" class="btn btn-primary">{{ __('Calculate') }}</button>
                    </form>

                    @isset($total)
                        <div class="dc-result-region">
                        <hr>
                        <h5>{{ __('Your Membership Dues') }}</h5>
                        <table class="table table-sm" data-dues-result>
                            <tbody>
                            @foreach($breakdown as $line)
                                <tr class="{{ ($line['bold'] ?? false) ? 'table-primary fw-bold' : '' }} {{ ($line['muted'] ?? false) ? 'text-muted' : '' }}">
                                    <td>{{ $line['label'] }}</td>
                                    <td class="text-end">€{{ number_format($line['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>

                        <div class="alert alert-info">
                            <strong>{{ __('Payment Communication') }}:</strong><br>
                            <code class="fs-5 user-select-all">{{ $communication }}</code>
                            <p class="mt-2 mb-0 small text-muted">{{ __('Use this exact string as the communication/reference when making your bank transfer.') }}</p>
                        </div>

                        @php $clubIban = \App\Models\ThemeSetting::get('club_iban'); @endphp
                        @if($clubIban)
                            <div class="alert alert-secondary">
                                <strong>{{ __('Bank Transfer Details') }}:</strong><br>
                                {{ __('Beneficiary') }}: {{ \App\Models\ThemeSetting::get('club_full_name', 'Diving Club') }}<br>
                                IBAN: <code>{{ $clubIban }}</code><br>
                                @php $clubBic = \App\Models\ThemeSetting::get('club_bic'); @endphp
                                @if($clubBic)BIC: <code>{{ $clubBic }}</code><br>@endif
                                @php $clubBank = \App\Models\ThemeSetting::get('club_bank_name'); @endphp
                                @if($clubBank){{ __('Bank') }}: {{ $clubBank }}<br>@endif
                                {{ __('Amount') }}: €{{ number_format($total, 2) }}<br>
                                {{ __('Communication') }}: <code>{{ $communication }}</code>
                            </div>
                        @else
                            <div class="alert alert-warning">{{ __('Club IBAN not configured. Ask an administrator to set it in Settings → Banking.') }}</div>
                        @endif

                        @auth
                            <form method="POST" action="{{ route('dues.commit') }}" data-confirm="{{ __('Commit to paying €:amount for :year?', ['amount' => number_format($total, 2), 'year' => $year]) }}">
                                @csrf
                                <input type="hidden" name="season_year" value="{{ $year }}">
                                <input type="hidden" name="status_id" value="{{ $statusId }}">
                                @foreach($selectedOptionals ?? [] as $slug)
                                    <input type="hidden" name="optionals[]" value="{{ $slug }}">
                                @endforeach
                                <button type="submit" class="btn btn-success">{{ __('I commit to paying this') }}</button>
                                @if($unclassified)
                                    <span class="text-muted small ms-2">{{ __('Recorded for bureau review — your profile status is not changed.') }}</span>
                                @endif
                            </form>
                        @endauth
                        </div>
                    @endisset
                </div>
            </div>
        </div>
    </div>
</x-layout>
