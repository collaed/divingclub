{{-- ClubCEP.eu — Membership renewal tab: licence info, FFESSM InfoLicencié QR + scanner --}}
@php $licences = $target->licences()->with('federation')->get(); $canEditLic = $viewer->can('manage members'); @endphp
<h6>{{ __('Membership Renewal') }}</h6>

@foreach($licences as $lic)
    <div class="card dc-card mb-3">
        <div class="card-body">
            <h6>{{ $lic->federation->acronym }} — {{ $lic->federation->full_name }}</h6>

            @if($canEditLic)
            <form method="POST" action="{{ route('profile.update.licence', $lic) }}" class="row g-2 mb-2">
                @csrf
                <div class="col-md-3">
                    <label class="form-label small">{{ __('Licence Number') }}</label>
                    <input type="text" name="licence_number" class="form-control form-control-sm" value="{{ $lic->licence_number }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">{{ __('Request Date') }}</label>
                    <div class="input-group input-group-sm">
                        <input type="date" name="licence_request_date" class="form-control" value="{{ $lic->licence_request_date?->format('Y-m-d') }}">
                        <button type="button" class="btn btn-outline-secondary" onclick="this.previousElementSibling.value='{{ date('Y-m-d') }}'">{{ __('Today') }}</button>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">{{ __('Season') }}</label>
                    <input type="text" name="season" class="form-control form-control-sm" value="{{ $lic->season }}" placeholder="2025-2026">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">{{ __('Pending') }}</label>
                    <select name="licence_request_pending" class="form-select form-select-sm">
                        <option value="0" {{ !$lic->licence_request_pending ? 'selected' : '' }}>{{ __('No') }}</option>
                        <option value="1" {{ $lic->licence_request_pending ? 'selected' : '' }}>{{ __('Yes') }}</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Save') }}</button>
                </div>
            </form>
            @else
            <div class="row">
                <div class="col-md-4"><strong>{{ __('Licence Number') }}:</strong> {{ $lic->licence_number ?? '—' }}</div>
                <div class="col-md-4"><strong>{{ __('Request Date') }}:</strong> {{ $lic->licence_request_date?->format('d/m/Y') ?? '—' }}</div>
                <div class="col-md-4"><strong>{{ __('Pending') }}:</strong> {{ $lic->licence_request_pending ? __('Yes') : __('No') }}</div>
            </div>
            @endif

            {{-- FFESSM InfoLicencié --}}
            @if($lic->federation->acronym === 'FFESSM' && $lic->licence_number)
                @php
                    $ffessmNumber = preg_replace('/^[A-Z]-\d{2}-/', '', $lic->licence_number);
                    $canEdit = $viewer->can('manage members') || $viewer->id === $target->id;
                    $canSeeQr = $viewer->id === $target->id || $viewer->isBureau() || $viewer->detail?->active_instructor;
                @endphp
                @if($lic->federation_key && $canSeeQr)
                    <div class="mt-3 p-3 bg-light rounded">
                        <div class="d-flex align-items-center gap-3">
                            <img src="{{ route('qr.federation', $lic) }}" alt="FFESSM QR" width="120" height="120" class="border rounded">
                            <div>
                                <strong>{{ __('FFESSM InfoLicencié') }}</strong><br>
                                <a href="https://infolicencie.ffessm.fr/Home/InfoLicence?number={{ $ffessmNumber }}&key={{ $lic->federation_key }}" target="_blank" class="small">
                                    @icon('🔗') {{ __('View licence on FFESSM') }}
                                </a>
                                <br><small class="text-muted">{{ __('Scan QR code to verify licence') }}</small>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="mt-2">
                        <small class="text-muted">ℹ@icon('️') {{ __('FFESSM verification key not set.') }}
                            @if($canEdit)
                                {{ __('Scan your FFESSM card QR code below to set it.') }}
                            @endif
                        </small>
                    </div>
                @endif

                {{-- Key edit form (member self + bureau) --}}
                @if($canEdit)
                    <form method="POST" action="{{ route('profile.update.federation-key', $lic) }}" class="mt-2" id="ffessm-key-form-{{ $lic->id }}">
                        @csrf
                        <div class="input-group input-group-sm" style="max-width:500px">
                            <span class="input-group-text">{{ __('FFESSM Key') }}</span>
                            <input type="text" name="federation_key" id="ffessm-key-{{ $lic->id }}" class="form-control font-monospace" value="{{ $lic->federation_key }}" placeholder="ABCDEF" maxlength="20" pattern="[A-Za-z0-9]+">
                            <button type="button" class="btn btn-outline-secondary" onclick="startQrScan({{ $lic->id }})" title="{{ __('Scan QR') }}">📷</button>
                            <button type="submit" class="btn btn-outline-primary">{{ __('Save') }}</button>
                        </div>
                        <small class="text-muted">{{ __('6-char key from the FFESSM card QR code, or use 📷 to scan it') }}</small>
                    </form>
                    {{-- Camera viewfinder (hidden until scan button pressed) --}}
                    <div id="qr-scanner-{{ $lic->id }}" class="mt-2" style="display:none; max-width:400px;">
                        <video id="qr-video-{{ $lic->id }}" style="width:100%; border-radius:.375rem;" playsinline></video>
                        <button type="button" class="btn btn-sm btn-outline-danger mt-1" onclick="stopQrScan({{ $lic->id }})">{{ __('Cancel') }}</button>
                    </div>
                @endif
            @endif

            {{-- Licence card (PDF) --}}
            @php $licCard = $target->documents()->where('category', 'licence_card')->where('file_path', 'LIKE', '%' . strtolower($lic->federation->acronym) . '%')->orWhere(function($q) use ($lic, $target) { $q->where('user_id', $target->id)->where('category', 'licence_card'); })->latest()->first(); @endphp
            @if($licCard && str_ends_with($licCard->original_filename, '.pdf'))
                <div class="mt-3">
                    <strong class="small">@icon('🪪') {{ __('Licence Card') }}</strong>
                    <iframe src="{{ route('profile.document.view', $licCard) }}" style="width:100%;max-width:500px;height:180px;border:1px solid #dee2e6;border-radius:0.5rem" loading="lazy"></iframe>
                </div>
            @elseif($licCard)
                <div class="mt-3">
                    <strong class="small">@icon('🪪') {{ __('Licence Card') }}</strong>
                    <a href="{{ route('profile.document.download', $licCard) }}" class="btn btn-sm btn-outline-primary ms-2">{{ __('Download') }}</a>
                </div>
            @endif
            {{-- FFESSM virtual licence card --}}
            @if($lic->federation->acronym === 'FFESSM' && $lic->licence_number)
                <div class="mt-3">
                    <strong class="small">@icon('🪪') {{ __('FFESSM Licence Card') }}</strong>
                    <div class="mt-2" style="display:flex;justify-content:flex-start">
                        @include('profile.partials.ffessm-card', ['licence' => $lic, 'user' => $target])
                    </div>
                </div>
            @endif
        </div>
    </div>
@endforeach

@if($licences->isEmpty())
    <p class="text-muted">{{ __('No licence records yet.') }}</p>
@endif

{{-- QR scanner script — uses native BarcodeDetector API (Chrome/Android/Safari 17+) --}}
@if($licences->where('federation.acronym', 'FFESSM')->isNotEmpty())
@push('scripts')
<script>
/**
 * FFESSM card QR scanner — extracts the federation key from the QR URL.
 * QR format: https://l.ffessm.fr/c.asp?id={number}_{KEY}
 * Uses the native BarcodeDetector API (no external library needed).
 */
let activeStream = null;
let activeScanner = null;

function startQrScan(licId) {
    const container = document.getElementById('qr-scanner-' + licId);
    const video = document.getElementById('qr-video-' + licId);
    container.style.display = 'block';

    if (!('BarcodeDetector' in window)) {
        alert('{{ __("Your browser does not support QR scanning. Please enter the key manually or use Chrome on Android.") }}');
        container.style.display = 'none';
        return;
    }

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(stream => {
            activeStream = stream;
            video.srcObject = stream;
            video.play();
            const detector = new BarcodeDetector({ formats: ['qr_code'] });
            activeScanner = setInterval(async () => {
                try {
                    const codes = await detector.detect(video);
                    for (const code of codes) {
                        const match = code.rawValue.match(/[?&]id=(\d+)_([A-Z]+)/);
                        if (match) {
                            document.getElementById('ffessm-key-' + licId).value = match[2];
                            stopQrScan(licId);
                            return;
                        }
                    }
                } catch(e) {}
            }, 300);
        })
        .catch(() => {
            alert('{{ __("Camera access denied.") }}');
            container.style.display = 'none';
        });
}

function stopQrScan(licId) {
    if (activeScanner) { clearInterval(activeScanner); activeScanner = null; }
    if (activeStream) { activeStream.getTracks().forEach(t => t.stop()); activeStream = null; }
    document.getElementById('qr-scanner-' + licId).style.display = 'none';
}
</script>
@endpush
@endif
