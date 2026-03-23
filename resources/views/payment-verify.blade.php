{{-- Payment verification page — user lands here after scanning signed QR | ClubCEP.eu --}}
<x-layout :title="__('Payment Verification')">
    <div class="row justify-content-center">
        <div class="col-md-6">
            @if($valid)
                <div class="card dc-card">
                    <div class="card-header bg-success text-white">
                        @icon('✅') {{ __('Verified Payment Request') }}
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-3">
                            @icon('🔒') {{ __('This payment request was cryptographically signed by :club. The details below are authentic.', ['club' => \App\Models\ThemeSetting::get('club_full_name', config('app.name'))]) }}
                        </p>

                        <table class="table table-sm">
                            <tr><td class="text-muted">{{ __('Beneficiary') }}</td><td class="fw-bold">{{ $beneficiary }}</td></tr>
                            <tr><td class="text-muted">IBAN</td><td><code>{{ $iban }}</code></td></tr>
                            <tr><td class="text-muted">BIC</td><td><code>{{ $bic }}</code></td></tr>
                            <tr><td class="text-muted">{{ __('Bank') }}</td><td>{{ $bank }}</td></tr>
                            <tr class="table-primary"><td class="text-muted fw-bold">{{ __('Amount') }}</td><td class="fw-bold fs-5">{{ number_format($amount, 2) }}€</td></tr>
                            <tr><td class="text-muted">{{ __('Communication') }}</td><td><code class="text-break">{{ $communication }}</code></td></tr>
                        </table>

                        <div class="d-grid gap-2 mt-3">
                            <button class="btn btn-primary" onclick="copyAll()">@icon('📋') {{ __('Copy payment details') }}</button>
                        </div>

                        <div class="alert alert-info small mt-3 mb-0 py-2">
                            @icon('🏦') {{ __('Open your banking app and create a new transfer with the details above.') }}
                        </div>
                    </div>
                    <div class="card-footer small text-muted text-center">
                        @icon('🔐') {{ __('Signed by') }} {{ \App\Models\ThemeSetting::get('club_full_name', config('app.name')) }} · {{ __('Domain verified via TLS certificate') }}
                    </div>
                </div>
            @else
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        @icon('❌') {{ __('Verification Failed') }}
                    </div>
                    <div class="card-body text-center">
                        <p class="fs-5 mb-2">@icon('⚠')️</p>
                        <p>{{ $error }}</p>
                        <p class="small text-muted">{{ __('Do NOT proceed with this payment. Contact the club if you believe this is an error.') }}</p>
                        <a href="{{ route('dues.show') }}" class="btn btn-outline-primary">{{ __('Go to Membership Fees page') }}</a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if($valid ?? false)
    <script>
    function copyAll() {
        const text = "IBAN: {{ $iban }}\nBIC: {{ $bic }}\nBeneficiary: {{ $beneficiary }}\nAmount: {{ number_format($amount, 2) }}€\nCommunication: {{ $communication }}";
        navigator.clipboard.writeText(text).then(() => {
            const btn = event.target;
            btn.textContent = '✅ {{ __("Copied!") }}';
            setTimeout(() => btn.textContent = '📋 {{ __("Copy payment details") }}', 2000);
        });
    }
    </script>
    @endif
</x-layout>
