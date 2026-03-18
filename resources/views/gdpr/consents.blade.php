<x-layout :title="__('Privacy & Data')">
    <h4 class="mb-4">{{ __('Privacy & Data Management') }}</h4>

    <div class="row">
        <div class="col-lg-6">
            <div class="card dc-card mb-4">
                <div class="card-header">{{ __('Consent Management') }}</div>
                <div class="card-body">
                    @foreach(['data_processing' => 'Data Processing (required for membership)', 'marketing' => 'Marketing Communications', 'photo_publication' => 'Photo Publication on Website'] as $type => $label)
                        @php $c = $consents[$type] ?? null; @endphp
                        <form method="POST" action="{{ route('gdpr.consent') }}" class="d-flex justify-content-between align-items-center border-bottom py-2">
                            @csrf
                            <input type="hidden" name="consent_type" value="{{ $type }}">
                            <div>
                                <strong>{{ __($label) }}</strong>
                                @if($c?->granted) <span class="badge bg-success">{{ __('Granted') }}</span> <small class="text-muted">{{ $c->granted_at?->format('d/m/Y') }}</small>
                                @else <span class="badge bg-secondary">{{ __('Not granted') }}</span> @endif
                            </div>
                            <div>
                                @if($c?->granted)
                                    <input type="hidden" name="granted" value="0">
                                    <button class="btn btn-sm btn-outline-danger">{{ __('Revoke') }}</button>
                                @else
                                    <input type="hidden" name="granted" value="1">
                                    <button class="btn btn-sm btn-outline-success">{{ __('Grant') }}</button>
                                @endif
                            </div>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card dc-card mb-4">
                <div class="card-header">{{ __('Your Data') }}</div>
                <div class="card-body">
                    <a href="{{ route('gdpr.export') }}" class="btn btn-outline-primary mb-3 w-100">📥 {{ __('Download My Data (JSON)') }}</a>
                    <hr>
                    <h6 class="text-danger">{{ __('Right to Erasure') }}</h6>
                    <p class="small text-muted">{{ __('Request deletion of all your personal data. This action is irreversible.') }}</p>
                    <a href="{{ route('gdpr.erasure') }}" class="btn btn-outline-danger btn-sm">{{ __('Request Data Erasure') }}</a>
                </div>
            </div>
        </div>
    </div>
</x-layout>
