{{-- Membership renewal tab: licence cards side by side, inline editing --}}
@php $licences = $target->licences()->with('federation')->get(); $canEditLic = $viewer->can('manage members'); @endphp
<h6>{{ __('Membership Renewal') }}</h6>

{{-- Licence cards side by side --}}
@php $licCard = $target->documents()->where('user_id', $target->id)->where('category', 'licence_card')->latest()->first(); @endphp
@if($licences->isNotEmpty())
<div class="d-flex flex-wrap gap-3 mb-4">
    @foreach($licences as $lic)
        @if($lic->federation->acronym === 'FLASSA' && $lic->licence_number)
            @include('profile.partials.flassa-card', ['licence' => $lic, 'user' => $target, 'pdfDoc' => $licCard])
        @elseif($lic->federation->acronym === 'FFESSM' && $lic->licence_number)
            @include('profile.partials.ffessm-card', ['licence' => $lic, 'user' => $target])
        @endif
    @endforeach
</div>

{{-- FFESSM link + key edit (compact, below cards) --}}
@foreach($licences as $lic)
    @if($lic->federation->acronym === 'FFESSM' && $lic->licence_number)
        @php
            $ffessmNumber = preg_replace('/^[A-Z]-\d{2}-/', '', $lic->licence_number);
            $canEdit = $viewer->can('manage members') || $viewer->id === $target->id;
        @endphp
        @if($lic->federation_key)
            <div class="mb-3 small">
                <a href="https://infolicencie.ffessm.fr/Home/InfoLicence?number={{ $ffessmNumber }}&key={{ $lic->federation_key }}" target="_blank" class="text-decoration-none">
                    @icon('🔗') {{ __('Verify on FFESSM InfoLicencié') }}
                </a>
            </div>
        @endif
        @if($canEdit)
            <form method="POST" action="{{ route('profile.update.federation-key', $lic) }}" class="mb-3">
                @csrf
                <div class="input-group input-group-sm" style="max-width:400px">
                    <span class="input-group-text">{{ __('FFESSM Key') }}</span>
                    <input type="text" name="federation_key" id="ffessm-key-{{ $lic->id }}" class="form-control font-monospace" value="{{ $lic->federation_key }}" placeholder="ABCDEF" maxlength="20">
                    <button type="button" class="btn btn-outline-secondary" onclick="startQrScan({{ $lic->id }})" title="{{ __('Scan QR') }}">📷</button>
                    <button type="submit" class="btn btn-outline-primary">{{ __('Save') }}</button>
                </div>
            </form>
            <div id="qr-scanner-{{ $lic->id }}" class="mb-3" style="display:none;max-width:400px">
                <video id="qr-video-{{ $lic->id }}" style="width:100%;border-radius:.375rem" playsinline></video>
                <button type="button" class="btn btn-sm btn-outline-danger mt-1" onclick="stopQrScan({{ $lic->id }})">{{ __('Cancel') }}</button>
            </div>
        @endif
    @endif
@endforeach
@endif

{{-- Bureau: inline licence editing --}}
@if($canEditLic)
<h6 class="mt-4">{{ __('Licence Details') }}</h6>
@foreach($licences as $lic)
    <form method="POST" action="{{ route('profile.update.licence', $lic) }}" class="card dc-card mb-2">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-auto"><strong class="small">{{ $lic->federation->acronym }}</strong></div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">{{ __('Licence #') }}</label>
                    <input type="text" name="licence_number" class="form-control form-control-sm" value="{{ $lic->licence_number }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">{{ __('Request Date') }}</label>
                    <div class="input-group input-group-sm">
                        <input type="date" name="licence_request_date" class="form-control" value="{{ $lic->licence_request_date?->format('Y-m-d') }}">
                        <button type="button" class="btn btn-outline-secondary" onclick="this.previousElementSibling.value='{{ date('Y-m-d') }}'">{{ __('Today') }}</button>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">{{ __('Season') }}</label>
                    <input type="text" name="season" class="form-control form-control-sm" value="{{ $lic->season }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">{{ __('Insurance') }}</label>
                    <select name="insurance_type" class="form-select form-select-sm">
                        <option value="">—</option>
                        @foreach(['Loisir 1','Loisir 2','Loisir 3','Loisir 1 Top','Loisir 2 Top','Loisir 3 Top','Aucune'] as $ins)
                            <option value="{{ $ins }}" {{ $lic->insurance_type === $ins ? 'selected' : '' }}>{{ $ins }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-0">{{ __('Pending') }}</label>
                    <select name="licence_request_pending" class="form-select form-select-sm">
                        <option value="0" {{ !$lic->licence_request_pending ? 'selected' : '' }}>{{ __('No') }}</option>
                        <option value="1" {{ $lic->licence_request_pending ? 'selected' : '' }}>{{ __('Yes') }}</option>
                    </select>
                </div>
                <div class="col-auto">@csrf <button type="submit" class="btn btn-sm btn-primary">{{ __('Save') }}</button></div>
            </div>
        </div>
    </form>
@endforeach
@elseif($licences->isNotEmpty())
<h6 class="mt-4">{{ __('Licence Details') }}</h6>
@foreach($licences as $lic)
    <div class="card dc-card mb-2">
        <div class="card-body py-2 small">
            <strong>{{ $lic->federation->acronym }}</strong> —
            {{ __('Licence') }}: {{ $lic->licence_number ?? '—' }} |
            {{ __('Season') }}: {{ $lic->season ?? '—' }} |
            {{ __('Requested') }}: {{ $lic->licence_request_date?->format('d/m/Y') ?? '—' }}
            @if($lic->insurance_type) | {{ __('Insurance') }}: <strong>{{ $lic->insurance_type }}</strong> @endif
            @if($lic->registration_date) | {{ __('Registered') }}: {{ \Carbon\Carbon::parse($lic->registration_date)->format('d/m/Y') }} @endif
            @if($lic->licence_request_pending) <span class="badge bg-warning text-dark">{{ __('Pending') }}</span> @endif
        </div>
    </div>
@endforeach
@endif

@if($licences->isEmpty())
    <p class="text-muted">{{ __('No licence records yet.') }}</p>
@endif

{{-- QR scanner script --}}
@if($licences->where('federation.acronym', 'FFESSM')->isNotEmpty())
@push('scripts')
<script>
let activeStream = null, activeScanner = null;
function startQrScan(licId) {
    const c = document.getElementById('qr-scanner-' + licId);
    const v = document.getElementById('qr-video-' + licId);
    c.style.display = 'block';
    if (!('BarcodeDetector' in window)) { alert('{{ __("QR scanning not supported. Enter key manually.") }}'); c.style.display='none'; return; }
    navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}}).then(s => {
        activeStream=s; v.srcObject=s; v.play();
        const d=new BarcodeDetector({formats:['qr_code']});
        activeScanner=setInterval(async()=>{try{const codes=await d.detect(v);for(const code of codes){const raw=code.rawValue;let key=null;let m=raw.match(/[?&]id=\d+_([A-Z0-9]{4,8})/);if(m)key=m[1];if(!key){m=raw.match(/key=([A-Z0-9]{4,8})/);if(m)key=m[1];}if(!key&&/^[A-Z0-9]{4,8}$/.test(raw))key=raw;if(key){document.getElementById('ffessm-key-'+licId).value=key;stopQrScan(licId);return;}alert('QR: '+raw.substring(0,100));stopQrScan(licId);return;}}catch(e){}},300);
    }).catch(()=>{alert('{{ __("Camera access denied.") }}');c.style.display='none';});
}
function stopQrScan(licId) {
    if(activeScanner){clearInterval(activeScanner);activeScanner=null;}
    if(activeStream){activeStream.getTracks().forEach(t=>t.stop());activeStream=null;}
    document.getElementById('qr-scanner-'+licId).style.display='none';
}
</script>
@endpush
@endif
