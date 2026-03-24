<x-layout :title="__('Profile') . ' — ' . $target->name">
    @php
        $isSelf = $viewer->id === $target->id;
        $canEdit = $canEdit ?? ($isSelf || $viewer->isBureau());
        $tierVault = $tierVault ?? ($isSelf || $viewer->isBureau());
        $tierManifest = $tierManifest ?? ($tierVault || $viewer->hasAnyRole(['instructor', 'assistant']));
        $d = $target->detail;
    @endphp

    @if($viewer->isBureauMaster() && !$isSelf)
        <div class="alert alert-warning py-2 mb-3">@icon('⚠️') {{ __('Editing as Bureau Master') }}: {{ $target->name }}</div>
    @endif

    {{-- Profile header --}}
    <div class="card dc-card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 text-center">
                    @if($d?->avatar_path)
                        <img src="{{ asset('storage/' . $d->avatar_path) }}" alt="{{ $target->name }}" class="rounded-circle mb-2" style="width:160px; height:160px; object-fit:cover;">
                    @else
                        <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center mb-2" style="width:160px; height:160px;">
                            <span class="text-white" style="font-size:3rem;">{{ strtoupper(substr($d?->first_name ?? '?', 0, 1) . substr($d?->last_name ?? '', 0, 1)) }}</span>
                        </div>
                    @endif
                    @if($canEdit)
                        <form method="POST" action="{{ route('profile.avatar.upload') }}" enctype="multipart/form-data" class="mt-1">
                            @csrf
                            <input type="file" name="avatar" accept="image/*" class="form-control form-control-sm mb-1" onchange="this.form.submit()">
                        </form>
                        @if($d?->avatar_path)
                            <form method="POST" action="{{ route('profile.avatar.delete') }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">{{ __('Remove Photo') }}</button>
                            </form>
                        @endif
                    @endif
                </div>
                <div class="col-md-9">
                    <h4 class="mb-1">{{ $target->name }}
                        @if(!$isSelf)
                            <a href="{{ route('contact.member', $target) }}" class="btn btn-sm btn-outline-primary ms-2">@icon('✉️') {{ __('Contact') }}</a>
                        @endif
                    </h4>
                    <div class="row mt-2">
                        <div class="col-sm-6">
                            <table class="table table-sm table-borderless mb-0">
                                {{-- Batch 2: Deck — visible to all members --}}
                                <tr><th class="text-muted" style="width:140px">{{ __('Status') }}</th><td>{{ $target->status?->name ?? '—' }}</td></tr>
                                <tr><th class="text-muted">{{ __('Nationality') }}</th><td>{{ $d?->nationality ?? '—' }}</td></tr>
                                <tr><th class="text-muted">{{ __('Member Since') }}</th><td>{{ $d?->adhesion_year ?? '—' }}</td></tr>
                                <tr><th class="text-muted">{{ __('Age') }}</th><td>
                                    @if($d?->date_of_birth)
                                        @if($tierManifest)
                                            {{ $d->date_of_birth->age }} {{ __('years') }}
                                        @else
                                            @php $age = $d->date_of_birth->age; $bracket = (int)floor($age / 5) * 5; @endphp
                                            {{ $bracket }}-{{ $bracket + 4 }}
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td></tr>
                            </table>
                        </div>
                        <div class="col-sm-6">
                            <table class="table table-sm table-borderless mb-0">
                                {{-- Batch 1: Vault — self + bureau only --}}
                                @if($tierVault)
                                <tr><th class="text-muted" style="width:140px">{{ __('Email') }}</th><td>{{ $target->primary_email }}</td></tr>
                                <tr><th class="text-muted">{{ __('Mobile') }}</th><td>{{ $d?->phone_mobile ?? '—' }}</td></tr>
                                @endif
                                {{-- Batch 2: Deck --}}
                                <tr><th class="text-muted">{{ __('Cert Level') }}</th><td>
                                    @php $primaryCert = $target->primaryCertification(); @endphp
                                    @if($primaryCert)
                                        <span class="badge bg-primary">{{ $primaryCert->code }}</span> <small>{{ $primaryCert->federation->acronym }}</small>
                                    @else
                                        {{ $d?->certification_level ?? '—' }}
                                    @endif
                                </td></tr>
                                <tr><th class="text-muted">{{ __('Medical') }}</th><td>
                                    <span class="badge bg-{{ $medicalStatus['badge'] }}">{{ __($medicalStatus['label']) }}</span>
                                    @if($tierManifest && $medicalStatus['cert']?->expiry_date)
                                        <small class="text-muted ms-1">→ {{ $medicalStatus['cert']->expiry_date->format('d/m/Y') }}</small>
                                    @endif
                                </td></tr>
                            </table>
                            @if($tierManifest && $d?->emergency_contact_name)
                                <div class="mt-2 small">
                                    <strong>{{ __('Emergency Contact') }}:</strong>
                                    {{ $d->emergency_contact_name }}
                                    @if($d->emergency_contact_phone) · {{ $d->emergency_contact_phone }}@endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs" role="tablist">
        @php
            $tabs = ['info' => __('Info')];
            if ($tierVault) {
                $tabs['private'] = __('Private Info');
            }
            $tabs['diving'] = __('Diving');
            if ($canEdit) {
                $tabs['language'] = __('Language');
            }
            if ($tierVault) {
                $tabs['medical'] = __('Medical Cert');
                $tabs['renewal'] = __('Membership Renewal');
            }
            $tabs['registrations'] = __('Registrations');
            if ($tierVault) {
                $tabs['equipment'] = __('Equipment on Loan');
            }
            $activeTab = old('tab', $tab);
        @endphp
        @foreach($tabs as $key => $label)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTab === $key ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-{{ $key }}" type="button" role="tab">{{ $label }}</button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content dc-tab-content">
        <div class="tab-pane fade {{ $activeTab === 'info' ? 'show active' : '' }}" id="tab-info" role="tabpanel">
            @include('profile.tabs.info', ['target' => $target, 'viewer' => $viewer, 'statuses' => $statuses, 'canEdit' => $canEdit])
        </div>
        @if(isset($tabs['private']))
        <div class="tab-pane fade {{ $activeTab === 'private' ? 'show active' : '' }}" id="tab-private" role="tabpanel">
            @include('profile.tabs.private', ['target' => $target, 'viewer' => $viewer])
        </div>
        @endif
        <div class="tab-pane fade {{ $activeTab === 'diving' ? 'show active' : '' }}" id="tab-diving" role="tabpanel">
            @include('profile.tabs.diving', ['target' => $target, 'viewer' => $viewer, 'canEdit' => $canEdit])
        </div>
        @if(isset($tabs['language']))
        <div class="tab-pane fade {{ $activeTab === 'language' ? 'show active' : '' }}" id="tab-language" role="tabpanel">
            @include('profile.tabs.language', ['target' => $target, 'viewer' => $viewer])
        </div>
        @endif
        @if(isset($tabs['medical']))
        <div class="tab-pane fade {{ $activeTab === 'medical' ? 'show active' : '' }}" id="tab-medical" role="tabpanel">
            @include('profile.tabs.medical', ['target' => $target, 'viewer' => $viewer])
        </div>
        @endif
        @if(isset($tabs['renewal']))
        <div class="tab-pane fade {{ $activeTab === 'renewal' ? 'show active' : '' }}" id="tab-renewal" role="tabpanel">
            @include('profile.tabs.renewal', ['target' => $target, 'viewer' => $viewer])
        </div>
        @endif
        <div class="tab-pane fade {{ $activeTab === 'registrations' ? 'show active' : '' }}" id="tab-registrations" role="tabpanel">
            @include('profile.tabs.registrations', ['target' => $target])
        </div>
        @if(isset($tabs['equipment']))
        <div class="tab-pane fade {{ $activeTab === 'equipment' ? 'show active' : '' }}" id="tab-equipment" role="tabpanel">
            @include('profile.tabs.equipment', ['target' => $target])
        </div>
        @endif
    </div>

    {{-- Email Management --}}
    <div class="card dc-card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ __('Email Addresses') }}</h5>
            @if($viewer->id === $target->id)
                <form method="POST" action="{{ route('password.request.send') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="email" value="{{ $target->primary_email }}">
                    <button class="btn btn-sm btn-outline-info">@icon('🔑') {{ __('Send Password Reset Link') }}</button>
                </form>
            @endif
        </div>
        <div class="card-body">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Email') }}</th><th>{{ __('Label') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($target->emails as $em)
                    <tr>
                        <td>{{ $em->email }} @if($em->is_primary) <span class="badge bg-primary">{{ __('Primary') }}</span> @endif</td>
                        <td>{{ $em->label }}</td>
                        <td>@if($em->is_verified) <span class="badge bg-success">{{ __('Verified') }}</span> @else <span class="badge bg-warning text-dark">{{ __('Unverified') }}</span> @endif</td>
                        <td class="text-end">
                            @if(!$em->is_primary && $em->is_verified)
                                <form method="POST" action="{{ route('profile.email.primary', $em) }}" class="d-inline">@csrf <button class="btn btn-sm btn-outline-primary">{{ __('Set Primary') }}</button></form>
                            @endif
                            @if(!$em->is_primary)
                                <form method="POST" action="{{ route('profile.email.delete', $em) }}" class="d-inline">@csrf @method('DELETE') <button class="btn btn-sm btn-outline-danger">{{ __('Remove') }}</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if($target->emails->count() < 5)
                <form method="POST" action="{{ route('profile.email.add') }}" class="row g-2 align-items-end mt-2">
                    @csrf
                    <div class="col-md-5"><input type="email" name="email" class="form-control" placeholder="{{ __('New email address') }}" required></div>
                    <div class="col-md-3"><input type="text" name="label" class="form-control" placeholder="{{ __('Label (optional)') }}"></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary">{{ __('Add') }}</button></div>
                </form>
            @endif
        </div>
    </div>
</x-layout>
