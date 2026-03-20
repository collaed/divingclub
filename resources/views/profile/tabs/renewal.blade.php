@php $licences = $target->licences()->with('federation')->get(); @endphp
<h6>{{ __('Membership Renewal') }}</h6>
<p class="text-muted small">{{ __('This tab is read-only for members. Bureau can edit licence details.') }}</p>

@foreach($licences as $lic)
    <div class="card dc-card mb-3">
        <div class="card-body">
            <h6>{{ $lic->federation->acronym }} — {{ $lic->federation->full_name }}</h6>
            <div class="row">
                <div class="col-md-4"><strong>{{ __('Licence Number') }}:</strong> {{ $lic->licence_number ?? '—' }}</div>
                <div class="col-md-4"><strong>{{ __('Request Date') }}:</strong> {{ $lic->licence_request_date?->format('d/m/Y') ?? '—' }}</div>
                <div class="col-md-4"><strong>{{ __('Pending') }}:</strong> {{ $lic->licence_request_pending ? __('Yes') : __('No') }}</div>
            </div>

            {{-- FFESSM InfoLicencié --}}
            @if($lic->federation->acronym === 'FFESSM' && $lic->licence_number)
                @php
                    $ffessmNumber = preg_replace('/^[A-Z]-\d{2}-/', '', $lic->licence_number);
                @endphp
                @if($lic->federation_key)
                    <div class="mt-3 p-3 bg-light rounded">
                        <div class="d-flex align-items-center gap-3">
                            <img src="{{ route('qr.federation', $lic) }}" alt="FFESSM QR" width="120" height="120" class="border rounded">
                            <div>
                                <strong>{{ __('FFESSM InfoLicencié') }}</strong><br>
                                <a href="https://infolicencie.ffessm.fr/Home/InfoLicence?number={{ $ffessmNumber }}&key={{ $lic->federation_key }}" target="_blank" class="small">
                                    🔗 {{ __('View licence on FFESSM') }}
                                </a>
                                <br><small class="text-muted">{{ __('Scan QR code to verify licence') }}</small>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="mt-2">
                        <small class="text-muted">ℹ️ {{ __('FFESSM verification key not set.') }}</small>
                    </div>
                @endif

                {{-- Bureau can set the federation key --}}
                @if($viewer->isBureauMaster())
                    <form method="POST" action="{{ route('profile.update.federation-key', $lic) }}" class="mt-2">
                        @csrf
                        <div class="input-group input-group-sm" style="max-width:400px">
                            <span class="input-group-text">{{ __('FFESSM Key') }}</span>
                            <input type="text" name="federation_key" class="form-control font-monospace" value="{{ $lic->federation_key }}" placeholder="ABCDEF" maxlength="20" pattern="[A-Za-z0-9]+">
                            <button class="btn btn-outline-primary">{{ __('Save') }}</button>
                        </div>
                        <small class="text-muted">{{ __('6-char key from the FFESSM InfoLicencié QR code') }}</small>
                    </form>
                @endif
            @endif
        </div>
    </div>
@endforeach

@if($licences->isEmpty())
    <p class="text-muted">{{ __('No licence records yet.') }}</p>
@endif
