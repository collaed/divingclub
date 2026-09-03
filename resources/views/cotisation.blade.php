{{-- Cotisation calculator — auto-fills logged-in user, generates bank transfer details | ClubCEP.eu --}}
<x-layout :title="__('Cotisation') . ' ' . $cfg['year']">
    <h4 class="mb-4">💳 {{ __('Cotisation') }} {{ $cfg['year'] }}</h4>

    @php
        $user = auth()->user();
        $detail = $user?->detail;
        $name = $detail ? ($detail->first_name . ' ' . $detail->last_name) : '';
        $taperPct = $cfg['taper_pct'] ?? 100;
        $isReduced = $taperPct < 100;
        // Compute the tapered amount for a full price, rounded up to the euro.
        $taperedAmount = fn ($full) => $isReduced ? (int) ceil($full * $taperPct / 100) : $full;
    @endphp

    {{-- User identity --}}
    @auth
        <div class="card dc-card mb-3">
            <div class="card-body py-2">
                <strong>{{ $name }}</strong>
                @if($detail?->address)
                    <br><span class="text-muted small">{{ $detail->address }}</span>
                @endif
            </div>
        </div>
    @else
        <div class="alert alert-info py-2">
            <a href="{{ route('login') }}">{{ __('Log in') }}</a> {{ __('to pre-fill your details and save your choices.') }}
        </div>
    @endauth

    <div class="card dc-card">
        <div class="card-body">
            <div id="cotisApp">
                {{-- CEP Membership --}}
                <h6 class="fw-bold">{{ __('CEP Membership') }} {{ $cfg['year'] }} <span class="text-muted small">({{ __('Required') }})</span></h6>
                @foreach($cfg['cep'] as $key => $opt)
                    @php $cepAmount = $taperedAmount($opt['amount']); @endphp
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="cep_type" id="cep_{{ $key }}" value="{{ $key }}"
                               data-amount="{{ $cepAmount }}"
                               data-label="{{ ucfirst($key) }}"
                               {{ $loop->first ? 'checked' : '' }}
                               onchange="recalc()">
                        <label class="form-check-label" for="cep_{{ $key }}">
                            {{ $opt['label'] }}
                            <span class="text-muted">— {{ $cepAmount }}€</span>
                            @if($isReduced && $cepAmount != $opt['amount'])
                                <span class="badge bg-success">{{ __('Reduced') }} ({{ $taperPct }}%)</span>
                            @endif
                        </label>
                    </div>
                @endforeach

                <hr>

                {{-- FFESSM Licence (auto-selected) --}}
                <h6 class="fw-bold">{{ __('FFESSM Licence') }} {{ $cfg['year'] }} <span class="text-muted small">({{ __('Auto-selected') }})</span></h6>
                @foreach($cfg['licence'] as $key => $opt)
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="licence_type" id="lic_{{ $key }}" value="{{ $key }}"
                               data-amount="{{ $opt['amount'] }}"
                               data-for="{{ implode(',', $opt['for']) }}"
                               disabled>
                        <label class="form-check-label" for="lic_{{ $key }}">
                            {{ $opt['label'] }} — {{ number_format($opt['amount'], 2) }}€
                        </label>
                    </div>
                @endforeach

                <hr>

                {{-- Insurance --}}
                <h6 class="fw-bold">{{ __('Individual Insurance') }}</h6>
                @foreach($cfg['insurance'] as $key => $opt)
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="insurance_type" id="ins_{{ $key }}" value="{{ $key }}"
                               data-amount="{{ $opt['amount'] }}"
                               data-label="{{ str_replace("Assurance ", "", $opt['label']) }}"
                               {{ $key === 'loisir1top' ? 'checked' : '' }}
                               onchange="recalc()">
                        <label class="form-check-label" for="ins_{{ $key }}">
                            {{ $opt['label'] }}
                            @if($opt['amount'] > 0)
                                <span class="text-muted">— {{ number_format($opt['amount'], 2) }}€</span>
                            @else
                                <span class="text-muted">— 0€</span>
                            @endif
                        </label>
                    </div>
                @endforeach

                <hr>

                {{-- Total + bank transfer details --}}
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm mb-0">
                            <tr><td>{{ __('CEP Membership') }}</td><td class="text-end fw-bold" id="sumCep">0€</td></tr>
                            <tr><td>{{ __('FFESSM Licence') }}</td><td class="text-end fw-bold" id="sumLic">0€</td></tr>
                            <tr><td>{{ __('Insurance') }}</td><td class="text-end fw-bold" id="sumIns">0€</td></tr>
                            <tr class="table-primary"><td class="fw-bold">{{ __('TOTAL') }}</td><td class="text-end fw-bold fs-5" id="sumTotal">0€</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body py-2 small">
                                <strong>{{ __('Bank Transfer Details') }}</strong>
                                <table class="table table-sm table-borderless mb-0 mt-1">
                                    <tr><td class="text-muted">IBAN:</td><td><code>{{ $cfg['iban'] }}</code></td></tr>
                                    <tr><td class="text-muted">BIC:</td><td><code>{{ $cfg['bic'] }}</code></td></tr>
                                    <tr><td class="text-muted">{{ __('Beneficiary') }}:</td><td>{{ $cfg['beneficiary'] }}</td></tr>
                                    <tr><td class="text-muted">{{ __('Bank') }}:</td><td>{{ $cfg['bank'] }}</td></tr>
                                    <tr><td class="text-muted">{{ __('Amount') }}:</td><td class="fw-bold" id="bankAmount">0€</td></tr>
                                    <tr><td class="text-muted">{{ __('Communication') }}:</td><td><code id="bankComm" class="text-break"></code></td></tr>
                                </table>
                                <div class="alert alert-warning py-1 px-2 mt-2 mb-0" style="font-size:0.75rem">
                                    ⚠️ {{ __('Please use the exact communication text above — it helps the treasurer reconcile payments.') }}
                                </div>

                                {{-- Signed payment QR (anti-quishing: URL to verified page, not raw bank details) --}}
                                <div class="text-center mt-3">
                                    <img id="sepaQr" src="" alt="Payment QR" class="border rounded p-1" style="max-width:180px;display:none">
                                    <p class="text-muted mt-1 mb-0" style="font-size:0.65rem">
                                        🔐 {{ __('Signed QR — links to a verified :club page with payment details.', ['club' => \App\Models\ThemeSetting::get('club_full_name', config('app.name'))]) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const userName = @json($name);
    const licenceMap = {};
    document.querySelectorAll('[name="licence_type"]').forEach(r => {
        (r.dataset.for || '').split(',').forEach(t => { licenceMap[t] = r; });
    });

    function recalc() {
        const cep = document.querySelector('[name="cep_type"]:checked');
        const ins = document.querySelector('[name="insurance_type"]:checked');
        const cepType = cep.value;
        const cepAmount = parseFloat(cep.dataset.amount);
        const insAmount = parseFloat(ins.dataset.amount);

        // Auto-select licence based on CEP type
        document.querySelectorAll('[name="licence_type"]').forEach(r => r.checked = false);
        const licRadio = licenceMap[cepType];
        if (licRadio) licRadio.checked = true;
        const licAmount = licRadio ? parseFloat(licRadio.dataset.amount) : 0;

        // Sympathisant gets no insurance
        if (cepType === 'sympathisant') {
            document.querySelectorAll('[name="insurance_type"]').forEach(r => {
                r.disabled = (r.value !== 'none');
                if (r.value === 'none') r.checked = true;
            });
        } else {
            document.querySelectorAll('[name="insurance_type"]').forEach(r => r.disabled = false);
        }

        const total = cepAmount + licAmount + (cepType === 'sympathisant' ? 0 : insAmount);

        document.getElementById('sumCep').textContent = cepAmount.toFixed(2) + '€';
        document.getElementById('sumLic').textContent = licAmount.toFixed(2) + '€';
        document.getElementById('sumIns').textContent = (cepType === 'sympathisant' ? 0 : insAmount).toFixed(2) + '€';
        document.getElementById('sumTotal').textContent = total.toFixed(2) + '€';
        document.getElementById('bankAmount').textContent = total.toFixed(2) + '€';

        // Communication string for bank transfer reconciliation
        let comm = userName || '{{ __("<Your Name>") }}';
        comm += ' Cotisation {{ $cfg["year"] }}';
        comm += ' ' + cep.dataset.label;
        if (cepType !== 'sympathisant' && ins.value !== 'none') {
            comm += ' ' + ins.dataset.label;
        }
        document.getElementById('bankComm').textContent = comm;

        // Update signed payment QR (URL to verified page, not raw EPC)
        const qrImg = document.getElementById('sepaQr');
        if (total > 0) {
            qrImg.src = '{{ route("qr.payment.signed") }}?amount=' + total.toFixed(2) + '&communication=' + encodeURIComponent(comm);
            qrImg.style.display = '';
        } else {
            qrImg.style.display = 'none';
        }
    }

    recalc();
    </script>
</x-layout>
