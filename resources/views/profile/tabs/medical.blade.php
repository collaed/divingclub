@php $medDocs = $target->documents->where('category', 'medical'); @endphp

{{-- Compliance status --}}
@php $ms = app(\App\Services\MedicalComplianceService::class)->getStatus($target); @endphp
<div class="alert alert-{{ $ms['badge'] === 'success' ? 'success' : ($ms['badge'] === 'warning' ? 'warning' : 'danger') }} d-flex align-items-center mb-3">
    <span class="badge bg-{{ $ms['badge'] }} me-2">{{ __($ms['label']) }}</span>
    @if($ms['cert'])
        {{ __('Certificate dated') }}: {{ $ms['cert']->date_established?->format('d/m/Y') ?? '—' }}
        @if($ms['cert']->expiry_date) · {{ __('Expires') }}: {{ $ms['cert']->expiry_date->format('d/m/Y') }} @endif
        @if($ms['cert']->is_verified) · <span class="text-success">@icon('✓') {{ __('Verified') }}</span> @endif
    @else
        {{ __('Please upload your medical certificate.') }}
    @endif
</div>

<h6>{{ __('Medical Certificates') }}</h6>
@foreach($medDocs as $doc)
    <div class="card dc-card mb-2">
        <div class="card-body py-2 d-flex justify-content-between align-items-center">
            <div>
                <a href="{{ route('profile.document.download', $doc) }}">{{ $doc->original_filename }}</a>
                @if($doc->cert_type) <span class="badge bg-light text-dark ms-1">{{ strtoupper($doc->cert_type) }}</span> @endif
                <small class="text-muted ms-2">{{ $doc->date_established?->format('d/m/Y') }}</small>
                @if($doc->expiry_date) <small class="ms-2">→ {{ $doc->expiry_date->format('d/m/Y') }}</small> @endif
                @if($doc->is_verified) <span class="badge bg-success ms-2">{{ __('Verified') }}</span> @endif
                @if(!$doc->is_current) <span class="badge bg-secondary ms-2">{{ __('Superseded') }}</span> @endif
                @if($doc->compliance_notes) <br><small class="text-muted">{{ $doc->compliance_notes }}</small> @endif
            </div>
            <div>
                @if($viewer->isBureauMaster() && !$doc->is_verified)
                    <form method="POST" action="{{ route('profile.document.verify', $doc) }}" class="d-flex align-items-center gap-2">
                        @csrf
                        <select name="cert_type" class="form-select form-select-sm" style="width:auto">
                            @foreach(['gp','ent','sport','other'] as $ct)
                                <option value="{{ $ct }}" {{ $doc->cert_type === $ct ? 'selected' : '' }}>{{ strtoupper($ct) }}</option>
                            @endforeach
                        </select>
                        <input type="date" name="date_established" class="form-control form-control-sm" style="width:auto" value="{{ $doc->date_established?->format('Y-m-d') }}" title="{{ __('Exam date') }}">
                        <button class="btn btn-sm btn-outline-success">{{ __('Verify') }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endforeach

@if($medDocs->isEmpty())
    <p class="text-muted">{{ __('No medical certificate uploaded.') }}</p>
@endif

<form method="POST" action="{{ route('profile.document.upload') }}" enctype="multipart/form-data" class="mt-3">
    @csrf
    <input type="hidden" name="category" value="medical">
    @if($target->id !== auth()->id())
        <input type="hidden" name="target_user_id" value="{{ $target->id }}">
    @endif
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">{{ __('Upload Medical Certificate') }}</label>
            <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png" required>
            @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ __('Type') }}</label>
            <select name="cert_type" class="form-select @error('cert_type') is-invalid @enderror">
                <option value="gp">{{ __('General (GP)') }}</option>
                <option value="ent">{{ __('ENT Specialist') }}</option>
                <option value="sport">{{ __('Sports Medicine') }}</option>
                <option value="other">{{ __('Other') }}</option>
            </select>
            @error('cert_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">{{ __('Certificate Date') }}</label>
            <input type="date" name="date_established" class="form-control @error('date_established') is-invalid @enderror" required>
            @error('date_established') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary">{{ __('Upload') }}</button>
        </div>
    </div>
</form>
