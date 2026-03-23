<x-layout :title="__('Profile') . ' — ' . $target->name">
    @if($viewer->isBureauMaster() && $viewer->id !== $target->id)
        <div class="alert alert-warning py-2 mb-3">@icon('⚠️') {{ __('Editing as Bureau Master') }}: {{ $target->name }}</div>
    @endif

    {{-- Profile header with photo (like old Joomla site) --}}
    <div class="card dc-card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 text-center">
                    @if($target->detail?->avatar_path)
                        <img src="{{ asset('storage/' . $target->detail->avatar_path) }}" alt="{{ $target->name }}" class="rounded-circle mb-2" style="width:160px; height:160px; object-fit:cover;">
                    @else
                        <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center mb-2" style="width:160px; height:160px;">
                            <span class="text-white" style="font-size:3rem;">{{ strtoupper(substr($target->detail?->first_name ?? '?', 0, 1) . substr($target->detail?->last_name ?? '', 0, 1)) }}</span>
                        </div>
                    @endif
                    @if($viewer->id === $target->id || $viewer->isBureauMaster())
                        <form method="POST" action="{{ route('profile.avatar.upload') }}" enctype="multipart/form-data" class="mt-1">
                            @csrf
                            <input type="file" name="avatar" accept="image/*" class="form-control form-control-sm mb-1" onchange="this.form.submit()">
                        </form>
                        @if($target->detail?->avatar_path)
                            <form method="POST" action="{{ route('profile.avatar.delete') }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">{{ __('Remove Photo') }}</button>
                            </form>
                        @endif
                    @endif
                </div>
                <div class="col-md-9">
                    <h4 class="mb-1">{{ $target->name }}
                        @if($viewer->id !== $target->id)
                            <a href="{{ route('contact.member', $target) }}" class="btn btn-sm btn-outline-primary ms-2">@icon('✉️') {{ __('Contact') }}</a>
                        @endif
                    </h4>
                    <div class="row mt-2">
                        <div class="col-sm-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><th class="text-muted" style="width:140px">{{ __('Status') }}</th><td>{{ $target->status?->name ?? '—' }}</td></tr>
                                <tr><th class="text-muted">{{ __('Role') }}</th><td><span class="badge bg-secondary">{{ $target->role?->name }}</span></td></tr>
                                <tr><th class="text-muted">{{ __('Member Since') }}</th><td>{{ $target->detail?->adhesion_year ?? '—' }}</td></tr>
                            </table>
                        </div>
                        <div class="col-sm-6">
                            <table class="table table-sm table-borderless mb-0">
                                @if($viewer->id === $target->id || $viewer->isBureau())
                                <tr><th class="text-muted" style="width:140px">{{ __('Email') }}</th><td>{{ $target->primary_email }}</td></tr>
                                <tr><th class="text-muted">{{ __('Mobile') }}</th><td>{{ $target->detail?->phone_mobile ?? '—' }}</td></tr>
                                @endif
                                <tr><th class="text-muted">{{ __('Cert Level') }}</th><td>
                                    @php $primaryCert = $target->primaryCertification(); @endphp
                                    @if($primaryCert)
                                        <span class="badge bg-primary">{{ $primaryCert->code }}</span> <small>{{ $primaryCert->federation->acronym }}</small>
                                    @else
                                        {{ $target->detail?->certification_level ?? '—' }}
                                    @endif
                                </td></tr>
                                <tr><th class="text-muted">{{ __('Medical') }}</th><td><span class="badge bg-{{ $medicalStatus['badge'] }}">{{ __($medicalStatus['label']) }}</span></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs" role="tablist">
        @php
            $tabs = [
                'info' => __('Info'),
                'private' => __('Private Info'),
                'diving' => __('Diving'),
                'language' => __('Language'),
                'medical' => __('Medical Cert'),
                'renewal' => __('Membership Renewal'),
                'registrations' => __('Registrations'),
                'equipment' => __('Equipment on Loan'),
            ];
            $activeTab = old('tab', $tab);
            if ($viewer->id !== $target->id && !$viewer->isBureauMaster()) {
                unset($tabs['private']);
            }
        @endphp
        @foreach($tabs as $key => $label)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTab === $key ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-{{ $key }}" type="button" role="tab">{{ $label }}</button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content dc-tab-content">
        <div class="tab-pane fade {{ $activeTab === 'info' ? 'show active' : '' }}" id="tab-info" role="tabpanel">
            @include('profile.tabs.info', ['target' => $target, 'viewer' => $viewer, 'statuses' => $statuses])
        </div>
        @if(isset($tabs['private']))
        <div class="tab-pane fade {{ $activeTab === 'private' ? 'show active' : '' }}" id="tab-private" role="tabpanel">
            @include('profile.tabs.private', ['target' => $target, 'viewer' => $viewer])
        </div>
        @endif
        <div class="tab-pane fade {{ $activeTab === 'diving' ? 'show active' : '' }}" id="tab-diving" role="tabpanel">
            @include('profile.tabs.diving', ['target' => $target, 'viewer' => $viewer])
        </div>
        <div class="tab-pane fade {{ $activeTab === 'language' ? 'show active' : '' }}" id="tab-language" role="tabpanel">
            @include('profile.tabs.language', ['target' => $target, 'viewer' => $viewer])
        </div>
        <div class="tab-pane fade {{ $activeTab === 'medical' ? 'show active' : '' }}" id="tab-medical" role="tabpanel">
            @include('profile.tabs.medical', ['target' => $target, 'viewer' => $viewer])
        </div>
        <div class="tab-pane fade {{ $activeTab === 'renewal' ? 'show active' : '' }}" id="tab-renewal" role="tabpanel">
            @include('profile.tabs.renewal', ['target' => $target, 'viewer' => $viewer])
        </div>
        <div class="tab-pane fade {{ $activeTab === 'registrations' ? 'show active' : '' }}" id="tab-registrations" role="tabpanel">
            @include('profile.tabs.registrations', ['target' => $target])
        </div>
        <div class="tab-pane fade {{ $activeTab === 'equipment' ? 'show active' : '' }}" id="tab-equipment" role="tabpanel">
            @include('profile.tabs.equipment', ['target' => $target])
        </div>
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
