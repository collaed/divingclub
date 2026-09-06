<x-admin-layout :title="__('Members')">
    <h4 class="mb-4">{{ __('Member Management') }}</h4>

    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-3">
            <input type="text" name="search" data-instant-search="table-members" class="form-control" placeholder="{{ __('Search name or email...') }}" value="{{ request('search') }}">
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
        <div class="col-md-2">
            <div class="form-check mt-2">
                <input type="checkbox" name="historic" value="1" id="historicToggle" class="form-check-input" data-autosubmit {{ $historic ? 'checked' : '' }}>
                <label class="form-check-label small" for="historicToggle">{{ __('Full historic (incl. former)') }}</label>
            </div>
        </div>
        <div class="col-md-1 text-end"></div>
        <div class="col-md-3 text-end">
            <div class="dropdown d-inline">
                <button class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">@icon('🏥') {{ __('Medical Export') }}</button>
                <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width:260px">
                    <li class="mb-2">
                        <select id="medFedSelect" class="form-select form-select-sm">
                            <option value="">{{ __('All federations') }}</option>
                            @foreach(\App\Models\Federation::orderBy('acronym')->get() as $fed)
                                <option value="{{ $fed->id }}">{{ $fed->acronym }}</option>
                            @endforeach
                        </select>
                    </li>
                    <li><a class="dropdown-item" href="#" onclick="location.href='{{ route('admin.medical-export') }}?federation_id='+document.getElementById('medFedSelect').value">@icon('📋') {{ __('Member List (CSV)') }}</a></li>
                    <li><a class="dropdown-item" href="#" onclick="location.href='{{ route('admin.medical-certificates') }}?federation_id='+document.getElementById('medFedSelect').value">@icon('📦') {{ __('Certificates (ZIP)') }}</a></li>
                </ul>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table id="table-members" class="table table-hover">
            <thead>
                <tr>
                    <th></th>
                    <th><x-sortable-th column="id" label="#" /></th>
                    <th><x-sortable-th column="name" :label="__('Name')" /></th>
                    <th><x-sortable-th column="email" :label="__('Email')" /></th>
                    <th>{{ __('Role') }}</th>
                    <th>{{ __('Set') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Medical') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($members as $m)
                    <tr data-href="{{ route('admin.profile.show', $m) }}">
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
                        <td><span class="badge bg-secondary">{{ $m->roles->first()?->name ?? '—' }}</span></td>
                        <td data-no-nav data-status-url="{{ route('admin.members.status.update', $m) }}">
                            <select class="form-select form-select-sm js-member-set" data-member="{{ $m->id }}" style="min-width:9rem"
                                    data-status-map='@json(($statusSets ?? collect())->mapWithKeys(fn ($set) => [$set->id => $set->statuses->pluck("id")])->toArray())'>
                                <option value="">{{ __('— none —') }}</option>
                                @foreach(($statusSets ?? []) as $set)
                                    <option value="{{ $set->id }}" {{ $m->status_set_id == $set->id ? 'selected' : '' }}>{{ $set->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td data-no-nav>
                            <select class="form-select form-select-sm js-member-status" data-member="{{ $m->id }}" style="min-width:9rem">
                                <option value="">{{ __('—') }}</option>
                                @foreach($statuses as $s)
                                    <option value="{{ $s->id }}" {{ $m->status_id == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </td>
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
</x-admin-layout>

@include("components.clickable-rows")

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    // Auto-submit the historic toggle.
    const historic = document.getElementById('historicToggle');
    if (historic) {
        historic.addEventListener('change', function () { historic.form.submit(); });
    }

    const table = document.getElementById('table-members');
    if (!table || !csrf) return;

    function saveRow(row, payload) {
        const url = row.querySelector('[data-status-url]')?.dataset.statusUrl;
        if (!url) return;
        fetch(url, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        })
        .then(r => r.json().then(d => ({ ok: r.ok, d: d })))
        .then(({ ok, d }) => {
            if (ok && d.ok) { if (typeof showToast === 'function') showToast('{{ __('✓ Saved') }}', 'success'); }
            else { if (typeof showToast === 'function') showToast(d.message || '{{ __('Save failed') }}', 'danger'); }
        })
        .catch(() => { if (typeof showToast === 'function') showToast('{{ __('Save failed') }}', 'danger'); });
    }

    function filterStatusOptions(setSelect) {
        const row = setSelect.closest('tr');
        const statusSelect = row.querySelector('.js-member-status');
        if (!statusSelect) return;
        const map = JSON.parse(setSelect.dataset.statusMap || '{}');
        const allowed = map[setSelect.value] || null;
        Array.from(statusSelect.options).forEach(function (opt) {
            if (opt.value === '') return;
            const show = !allowed || allowed.map(String).includes(opt.value);
            opt.hidden = !show;
            opt.disabled = !show;
        });
    }

    table.addEventListener('change', function (e) {
        const setSel = e.target.closest('.js-member-set');
        const statusSel = e.target.closest('.js-member-status');
        if (setSel) {
            filterStatusOptions(setSel);
            saveRow(setSel.closest('tr'), { status_set_id: setSel.value || null });
        } else if (statusSel) {
            saveRow(statusSel.closest('tr'), { status_id: statusSel.value || null });
        }
    });

    // Initial constrain of each row's status options to its set.
    table.querySelectorAll('.js-member-set').forEach(filterStatusOptions);
})();
</script>
@endpush
