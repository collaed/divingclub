{{-- Membership renewal tab: licence cards side by side, inline editing --}}
@php $licences = $target->licences()->with('federation')->get(); $canEditLic = $viewer->can('manage members'); @endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h6 class="mb-0">{{ __('Licence Overview') }}</h6>
    <a href="{{ route('dues.show') }}" class="btn btn-outline-primary btn-sm">@icon('💳') {{ __('Membership fees') }}</a>
</div>

{{-- Licence cards side by side --}}
@php $licCard = $target->documents()->where('user_id', $target->id)->where('category', 'licence_card')->latest()->first(); @endphp
@if($licences->isNotEmpty())
<div class="d-flex flex-wrap gap-3 mb-4">
    @foreach($licences as $lic)
        @if($lic->federation->acronym === 'FLASSA' && $lic->licence_number && ($lic->scan_image_path || $licCard))
            {{-- The real card: the intake scan, or the member's own licence PDF rendered to an image. --}}
            <div>
                <a href="{{ $licCard ? route('profile.document.view', $licCard) : route('profile.licence.scan', $lic) }}" target="_blank" rel="noopener">
                    <img src="{{ route('profile.licence.scan', $lic) }}" alt="{{ __('FLASSA licence card') }}" class="rounded shadow-sm" style="max-width: 420px; height: auto;">
                </a>
                @if($lic->card_issued_at)
                    <div class="small text-muted mt-1">{{ __('Issued by FLASSA on :date', ['date' => $lic->card_issued_at->format('d/m/Y')]) }}</div>
                @endif
            </div>
        @elseif($lic->federation->acronym === 'FLASSA' && $lic->licence_number)
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

{{-- Add a new licence for this member (any federation, incl. FLASSA) --}}
@php
    $existingFedIds = $licences->pluck('federation_id')->all();
    $addableFederations = \App\Models\Federation::visible()
        ->whereNotIn('id', $existingFedIds)
        ->orderBy('acronym')
        ->get();
@endphp
@if($addableFederations->isNotEmpty())
    <form method="POST" action="{{ route('profile.store.licence', $target) }}" class="card dc-card mb-2 border-primary-subtle">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-auto"><strong class="small text-primary">@icon('➕') {{ __('Add licence') }}</strong></div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">{{ __('Federation') }}</label>
                    <select name="federation_id" class="form-select form-select-sm" required>
                        <option value="">{{ __('Select…') }}</option>
                        @foreach($addableFederations as $fed)
                            <option value="{{ $fed->id }}">{{ $fed->acronym }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">{{ __('Licence #') }}</label>
                    <input type="text" name="licence_number" class="form-control form-control-sm" value="{{ old('licence_number') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">{{ __('Season') }}</label>
                    <input type="text" name="season" class="form-control form-control-sm" value="{{ old('season') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">{{ __('Insurance') }}</label>
                    <select name="insurance_type" class="form-select form-select-sm">
                        <option value="">—</option>
                        @foreach(['Loisir 1','Loisir 2','Loisir 3','Loisir 1 Top','Loisir 2 Top','Loisir 3 Top','Aucune'] as $ins)
                            <option value="{{ $ins }}" {{ old('insurance_type') === $ins ? 'selected' : '' }}>{{ $ins }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">{{ __('Request Date') }}</label>
                    <div class="input-group input-group-sm">
                        <input type="date" name="licence_request_date" class="form-control" value="{{ old('licence_request_date') }}">
                        <button type="button" class="btn btn-outline-secondary" onclick="this.previousElementSibling.value='{{ date('Y-m-d') }}'">{{ __('Today') }}</button>
                    </div>
                </div>
                <div class="col-auto">@csrf <button type="submit" class="btn btn-sm btn-success">{{ __('Add') }}</button></div>
            </div>
        </div>
    </form>
@endif
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

@if($viewer->hasRole('bureau_master'))
<hr class="my-4">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
    <h6 class="mb-0">{{ __('Cotisation bank payments') }}</h6>
    <a href="{{ route('admin.ledger.index') }}" class="small">{{ __('Open the ledger') }}</a>
</div>
<p class="small text-muted">
    {{ __('Cotisation-tagged bank lines, to link to this member — a couple or a parent and child can pay both cotisations in one transfer, so a line already linked to someone else can still be linked here too. Category and insurance are read straight from the bank communication text when it names them.') }}
</p>
@php $showRoute = $viewer->id === $target->id ? route('profile.show') : route('admin.profile.show', $target); @endphp
<form method="GET" action="{{ $showRoute }}" class="d-flex flex-wrap gap-2 align-items-center mb-2">
    <input type="hidden" name="tab" value="renewal">
    <input type="text" name="cot_search" class="form-control form-control-sm" style="max-width:16rem" placeholder="{{ __('Search name, amount, communication…') }}" value="{{ $cotSearch }}">
    <div class="form-check">
        <input type="checkbox" name="show_identified" value="1" id="cot-show-identified" class="form-check-input" onchange="this.form.submit()" @checked($showIdentified)>
        <label class="form-check-label small" for="cot-show-identified">{{ __('Show also those already identified') }}</label>
    </div>
    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Search') }}</button>
</form>
@if($cotisationCandidates->isEmpty())
    <p class="small text-muted">{{ $showIdentified ? __('No cotisation lines found.') : __('Nothing unidentified — try "Show also those already identified", or search.') }}</p>
@else
<div class="table-responsive">
    <table class="table table-sm">
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Communication') }}</th><th>{{ __('Derived') }}</th><th>{{ __('Linked to') }}</th><th></th></tr></thead>
        <tbody>
        @foreach($cotisationCandidates as $tx)
            @php
                $derived = $cotisationDerived[$tx->id] ?? ['categoryLabel' => null, 'insuranceLabel' => null];
                $linkedToTarget = $tx->members->contains('id', $target->id);
            @endphp
            <tr>
                <td class="small">{{ $tx->transaction_date->format('d/m/Y') }}</td>
                <td class="small">{{ number_format((float) $tx->amount, 2, ',', ' ') }}</td>
                <td class="small">{{ $tx->communication() ?: $tx->counterparty_name }}</td>
                <td class="small">
                    @if($derived['categoryLabel'] || $derived['insuranceLabel'])
                        {{ $derived['categoryLabel'] }}
                        @if($derived['insuranceLabel'])<br>{{ $derived['insuranceLabel'] }}@endif
                    @else
                        <span class="text-muted fst-italic">{{ __('not detected') }}</span>
                    @endif
                </td>
                <td class="small">{{ $tx->members->map(fn ($m) => $m->initials())->implode(', ') ?: '—' }}</td>
                <td>
                    @if($linkedToTarget)
                        <form method="POST" action="{{ route('admin.profile.cotisation.unlink', [$target, $tx]) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Unlink') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.profile.cotisation.link', [$target, $tx]) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Link to this member') }}</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@if($cotisationCandidates->count() === 50)
    <p class="small text-muted">{{ __('Showing the 50 most recent — narrow with search to find an older one.') }}</p>
@endif
@endif
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
