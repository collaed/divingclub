<x-layout :title="__('Members')">
    <h4 class="mb-4">{{ __('Member Management') }}</h4>

    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control" placeholder="{{ __('Search name or email...') }}" value="{{ request('search') }}">
        </div>
        <div class="col-md-2">
            <select name="status_id" class="form-select">
                <option value="">{{ __('All Statuses') }}</option>
                @foreach($statuses as $s)
                    <option value="{{ $s->id }}" {{ request('status_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="role_id" class="form-select">
                <option value="">{{ __('All Roles') }}</option>
                @foreach($roles as $r)
                    <option value="{{ $r->id }}" {{ request('role_id') == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
            <a href="{{ route('admin.members.index') }}" class="btn btn-outline-secondary">{{ __('Clear') }}</a>
        </div>
        <div class="col-md-3 text-end">
            <div class="dropdown d-inline">
                <button class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">🏥 {{ __('Medical Export') }}</button>
                <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width:260px">
                    <li class="mb-2">
                        <select id="medFedSelect" class="form-select form-select-sm">
                            <option value="">{{ __('All federations') }}</option>
                            @foreach(\App\Models\Federation::orderBy('acronym')->get() as $fed)
                                <option value="{{ $fed->id }}">{{ $fed->acronym }}</option>
                            @endforeach
                        </select>
                    </li>
                    <li><a class="dropdown-item" href="#" onclick="location.href='{{ route('admin.medical-export') }}?federation_id='+document.getElementById('medFedSelect').value">📋 {{ __('Member List (CSV)') }}</a></li>
                    <li><a class="dropdown-item" href="#" onclick="location.href='{{ route('admin.medical-certificates') }}?federation_id='+document.getElementById('medFedSelect').value">📦 {{ __('Certificates (ZIP)') }}</a></li>
                </ul>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th></th>
                    <th><x-sortable-th column="id" label="#" /></th>
                    <th><x-sortable-th column="name" :label="__('Name')" /></th>
                    <th><x-sortable-th column="email" :label="__('Email')" /></th>
                    <th>{{ __('Role') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Medical') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($members as $m)
                    <tr>
                        <td style="width:40px">
                            @if($m->detail?->avatar_path)
                                <img src="{{ asset('storage/' . $m->detail->avatar_path) }}" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;">
                            @else
                                <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;">
                                    <span class="text-white" style="font-size:0.7rem;">{{ strtoupper(substr($m->detail?->first_name ?? '?', 0, 1) . substr($m->detail?->last_name ?? '', 0, 1)) }}</span>
                                </div>
                            @endif
                        </td>
                        <td>{{ $m->detail?->first_name }} {{ $m->detail?->last_name }}</td>
                        <td>{{ $m->primary_email }}</td>
                        <td><span class="badge bg-secondary">{{ $m->role?->name }}</span></td>
                        <td>{{ $m->status?->name ?? '—' }}</td>
                        <td>
                            @php $mMed = app(\App\Services\MedicalComplianceService::class)->getStatus($m); @endphp
                            <span class="badge bg-{{ $mMed['badge'] }}" style="font-size:0.65rem;">{{ __($mMed['label']) }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.profile.show', $m) }}" class="btn btn-sm btn-outline-primary">{{ __('View') }}</a>
                            <form method="POST" action="{{ route('admin.send-reset', $m) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-info" title="{{ __('Send password reset link') }}">🔑</button>
                            </form>
                            <form method="POST" action="{{ route('admin.impersonate', $m) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-warning">{{ __('Impersonate') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <x-per-page :current="request('per_page', 25)" />
        <div>{{ $members->links() }}</div>
    </div>
</x-layout>
