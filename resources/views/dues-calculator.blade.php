<x-layout :title="__('Membership Dues Calculator')">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card dc-card">
                <div class="card-header">{{ __('Membership Dues Calculator') }}</div>
                <div class="card-body">
                    <p class="text-muted">{{ __('Calculate your membership dues and get the payment communication string for your bank transfer.') }}</p>

                    <form method="POST" action="{{ route('dues.calculate') }}">
                        @csrf
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Season Year') }}</label>
                                <input type="text" name="season_year" class="form-control @error('season_year') is-invalid @enderror" value="{{ $year }}" required>
                                @error('season_year') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Last Name') }}</label>
                                <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ $lastName ?? '' }}" required placeholder="DUPONT">
                                @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('First Name') }}</label>
                                <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ $firstName ?? '' }}" required placeholder="Marie">
                                @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-5">
                                <label class="form-label">{{ __('Member Status') }}</label>
                                <select name="status_id" class="form-select @error('status_id') is-invalid @enderror" required>
                                    <option value="">{{ __('Select your status...') }}</option>
                                    @foreach($statuses as $s)
                                        <option value="{{ $s->id }}" {{ ($statusId ?? '') == $s->id ? 'selected' : '' }}>
                                            {{ $s->name }}
                                            @if(isset($fees[$s->id])) — €{{ number_format($fees[$s->id]->amount, 2) }} @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('status_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        @if($optionals->count())
                            <div class="mb-3">
                                <label class="form-label">{{ __('Optional Add-ons') }}</label>
                                @php $rendered = []; @endphp
                                @foreach($optionals as $opt)
                                    @if($opt->radio_group && !in_array($opt->radio_group, $rendered))
                                        @php $rendered[] = $opt->radio_group; $groupItems = $optionals->where('radio_group', $opt->radio_group); @endphp
                                        <div class="ms-2 mb-2">
                                            <div class="form-check">
                                                <input type="radio" name="optionals_{{ $opt->radio_group }}" value="" class="form-check-input" id="opt_{{ $opt->radio_group }}_none"
                                                    {{ !collect($selectedOptionals ?? [])->intersect($groupItems->pluck('slug'))->count() ? 'checked' : '' }}>
                                                <label class="form-check-label text-muted" for="opt_{{ $opt->radio_group }}_none">{{ __('None') }}</label>
                                            </div>
                                            @foreach($groupItems as $gi)
                                                <div class="form-check">
                                                    <input type="radio" name="optionals_{{ $gi->radio_group }}" value="{{ $gi->slug }}" class="form-check-input"
                                                           id="opt_{{ $gi->slug }}" {{ in_array($gi->slug, $selectedOptionals ?? []) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="opt_{{ $gi->slug }}">
                                                        {{ $gi->name }} — €{{ number_format($gi->amount, 2) }}
                                                        @if($gi->description) <small class="text-muted">({{ $gi->description }})</small> @endif
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    @elseif(!$opt->radio_group)
                                        <div class="form-check">
                                            <input type="checkbox" name="optionals[]" value="{{ $opt->slug }}" class="form-check-input"
                                                   id="opt_{{ $opt->slug }}" {{ in_array($opt->slug, $selectedOptionals ?? []) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="opt_{{ $opt->slug }}">
                                                {{ $opt->name }} — €{{ number_format($opt->amount, 2) }}
                                                @if($opt->description) <small class="text-muted">({{ $opt->description }})</small> @endif
                                            </label>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        <button type="submit" class="btn btn-primary">{{ __('Calculate') }}</button>
                    </form>

                    @if(isset($total))
                        <hr>
                        <h5>{{ __('Your Membership Dues') }}</h5>
                        <table class="table table-sm">
                            <tbody>
                            @foreach($breakdown as $line)
                                <tr class="{{ ($line['bold'] ?? false) ? 'fw-bold' : '' }}">
                                    <td>{{ $line['label'] }}</td>
                                    <td class="text-end">€{{ number_format($line['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                                <tr class="table-primary fw-bold">
                                    <td>{{ __('Total Due') }}</td>
                                    <td class="text-end">€{{ number_format($total, 2) }}</td>
                                </tr>
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
                                {{ __('Beneficiary') }}: {{ $theme['club_full_name'] ?? 'Club Européen de Plongée' }}<br>
                                IBAN: <code>{{ $clubIban }}</code><br>
                                {{ __('Amount') }}: €{{ number_format($total, 2) }}<br>
                                {{ __('Communication') }}: <code>{{ $communication }}</code>
                            </div>

                            <div class="text-center mt-3">
                                <p class="fw-bold mb-2">{{ __('Scan to pay with your banking app') }}</p>
                                <img src="{{ route('qr.sepa.public', ['amount' => $total, 'communication' => $communication]) }}" alt="SEPA QR" class="border rounded p-2" style="max-width:250px">
                                <p class="small text-muted mt-2">{{ __('EPC QR Code — compatible with most European banking apps') }}</p>
                            </div>
                        @else
                            <div class="alert alert-warning">{{ __('Club IBAN not configured. Ask an administrator to set it in Settings → Banking.') }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layout>
