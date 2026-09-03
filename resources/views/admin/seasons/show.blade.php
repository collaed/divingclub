<x-admin-layout :title="$season->name">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">{{ $season->name }} ({{ $season->year }}) @if($season->is_active) <span class="badge bg-success">{{ __('Active') }}</span> @endif</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.seasons.preview', $season) }}" class="btn btn-sm btn-outline-info">{{ __('Preview Schedule') }}</a>
            <form method="POST" action="{{ route('admin.seasons.generate', $season) }}" data-confirm="{{ __('Generate all events from patterns?') }}" data-confirm-style="primary" data-confirm-btn="{{ __('Generate') }}">
                @csrf
                <button class="btn btn-sm btn-primary">{{ __('Generate Events') }}</button>
            </form>
        </div>
    </div>

    <p class="text-muted">{{ $season->start_date->format('d/m/Y') }} — {{ $season->end_date->format('d/m/Y') }}</p>

    <div class="row">
        {{-- Weekly Patterns --}}
        <div class="col-lg-6">
            <div class="card dc-card mb-4">
                <div class="card-header">{{ __('Weekly Patterns') }}</div>
                <div class="card-body">
                    <div id="patternList">
                        @foreach($season->patterns as $p)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2" data-id="{{ $p->id }}">
                            <div>
                                <span class="badge" style="background:{{ $p->color_hex ?? '#6c757d' }}">{{ ucfirst($p->event_type) }}</span>
                                <strong>{{ $p->dayName() }}</strong> {{ $p->start_time }}{{ $p->end_time ? '—'.$p->end_time : '' }}
                                — {{ $p->title }}
                                @if($p->location) <small class="text-muted">({{ $p->location }})</small> @endif
                                @if($p->whatsapp_group_url) <span class="badge bg-success">WhatsApp</span> @endif
                                @if($p->registration_opens_days_before) <span class="badge bg-info">{{ __('Opens :d days before', ['d' => $p->registration_opens_days_before]) }}</span> @endif
                            </div>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-outline-primary btn-edit-pattern"
                                    data-url="{{ route('admin.seasons.pattern.update', $p) }}"
                                    data-pattern="{{ json_encode($p->only(['id','day_of_week','start_time','end_time','event_type','title','location','description','max_participants','estimated_cost','registration_opens_days_before','registration_closes_days_before','color_hex','whatsapp_group_url'])) }}">{{ __('Edit') }}</button>
                                <button class="btn btn-sm btn-outline-danger btn-del-pattern" data-url="{{ route('admin.seasons.pattern.destroy', $p) }}">&#x2715;</button>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <form id="patternForm" class="mt-3">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <select name="day_of_week" class="form-select form-select-sm" required>
                                    @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $i => $d)
                                        <option value="{{ $i }}">{{ __($d) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2"><input type="text" name="start_time" data-picker="time" class="form-control form-control-sm" placeholder="19:00" required></div>
                            <div class="col-md-2"><input type="text" name="end_time" data-picker="time" class="form-control form-control-sm" placeholder="21:00"></div>
                            <div class="col-md-2">
                                <select name="event_type" class="form-select form-select-sm" required>
                                    @foreach(['pool','dive','training','theory','social'] as $t)
                                        <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3"><input type="text" name="title" class="form-control form-control-sm" placeholder="{{ __('Title') }}" required></div>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-md-5"><input type="text" name="location" class="form-control form-control-sm" placeholder="{{ __('Location') }}"></div>
                            <div class="col-md-2"><input type="number" name="max_participants" class="form-control form-control-sm" placeholder="{{ __('Max') }}" min="1"></div>
                            <div class="col-md-2"><input type="number" name="registration_opens_days_before" class="form-control form-control-sm" placeholder="{{ __('Opens X days before') }}" min="1"></div>
                            <div class="col-md-2"><input type="color" name="color_hex" class="form-control form-control-sm form-control-color" value="#0077be"></div>
                            <div class="col-md-1"><button type="submit" class="btn btn-sm btn-primary w-100">{{ __('Add') }}</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Holidays --}}
        <div class="col-lg-6">
            <div class="card dc-card mb-4">
                <div class="card-header">{{ __('Holidays & Breaks') }}</div>
                <div class="card-body">
                    <div id="holidayList">
                        @foreach($season->holidays as $h)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2" data-id="{{ $h->id }}">
                            <div>
                                {{ $h->name }}
                                <small class="text-muted">{{ $h->start_date->format('d/m') }} — {{ $h->end_date->format('d/m/Y') }}</small>
                                @if($h->is_adhoc) <span class="badge bg-secondary">{{ __('Ad-hoc') }}</span> @endif
                            </div>
                            <button class="btn btn-sm btn-outline-danger btn-del-holiday" data-url="{{ route('admin.seasons.holiday.destroy', $h) }}">&#x2715;</button>
                        </div>
                        @endforeach
                    </div>

                    <form id="holidayForm" class="mt-3">
                        <div class="row g-2">
                            <div class="col-md-4"><input type="text" name="name" class="form-control form-control-sm" placeholder="{{ __('Holiday name') }}" required></div>
                            <div class="col-md-6">
                                <input type="text" id="holidayRange" class="form-control form-control-sm" data-picker="daterange" data-range-start="#holStart" data-range-end="#holEnd" placeholder="{{ __('Select dates') }}">
                                <input type="hidden" name="start_date" id="holStart">
                                <input type="hidden" name="end_date" id="holEnd">
                            </div>
                            <div class="col-md-2">
                                <div class="form-check mt-1"><input type="hidden" name="is_adhoc" value="0"><input type="checkbox" name="is_adhoc" value="1" class="form-check-input"><label class="form-check-label small">{{ __('Ad-hoc') }}</label></div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary mt-2">{{ __('Add Holiday') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Fee Taper Schedule --}}
    <div class="row">
        <div class="col-lg-8">
            <div class="card dc-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>💶 {{ __('Membership Fee Taper') }}</span>
                    <small class="text-muted">{{ __('Reduces only the club-retained membership component') }}</small>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        {{ __('Season-relative cutoffs. From season start the rate is 100%. Each cutoff (month-day) sets a new percentage from that date onward. Example: 01 Apr → 50%, 01 Aug → 100%.') }}
                    </p>
                    <form method="POST" action="{{ route('admin.seasons.taper.update', $season) }}">
                        @csrf
                        <div id="taperRows">
                            @php $tiers = $season->fee_taper_tiers ?? []; @endphp
                            @forelse($tiers as $tier)
                                @php [$mm, $dd] = array_pad(explode('-', $tier['from']), 2, '01'); @endphp
                                <div class="row g-2 mb-2 taper-row align-items-center">
                                    <div class="col-auto"><label class="small text-muted mb-0">{{ __('From') }}</label></div>
                                    <div class="col-auto"><input type="text" name="from[]" class="form-control form-control-sm" style="width:90px" value="{{ $tier['from'] }}" placeholder="MM-DD" pattern="\d{2}-\d{2}" required></div>
                                    <div class="col-auto"><label class="small text-muted mb-0">{{ __('Rate') }}</label></div>
                                    <div class="col-auto"><div class="input-group input-group-sm" style="width:100px"><input type="number" name="pct[]" class="form-control" min="0" max="100" value="{{ $tier['pct'] }}" required><span class="input-group-text">%</span></div></div>
                                    <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger taper-del">&#x2715;</button></div>
                                </div>
                            @empty
                            @endforelse
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="taperAdd">+ {{ __('Add cutoff') }}</button>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Save Taper Schedule') }}</button>
                    </form>
                    <template id="taperRowTpl">
                        <div class="row g-2 mb-2 taper-row align-items-center">
                            <div class="col-auto"><label class="small text-muted mb-0">{{ __('From') }}</label></div>
                            <div class="col-auto"><input type="text" name="from[]" class="form-control form-control-sm" style="width:90px" placeholder="MM-DD" pattern="\d{2}-\d{2}" required></div>
                            <div class="col-auto"><label class="small text-muted mb-0">{{ __('Rate') }}</label></div>
                            <div class="col-auto"><div class="input-group input-group-sm" style="width:100px"><input type="number" name="pct[]" class="form-control" min="0" max="100" required><span class="input-group-text">%</span></div></div>
                            <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger taper-del">&#x2715;</button></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Pattern Modal --}}
    <div class="modal fade" id="editPatternModal" tabindex="-1" aria-labelledby="editPatternModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPatternModalLabel">{{ __('Edit Pattern') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <form id="editPatternForm">
                    <div class="modal-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Day') }}</label>
                                <select name="day_of_week" class="form-select" required>
                                    @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $i => $d)
                                        <option value="{{ $i }}">{{ __($d) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Start Time') }}</label>
                                <input type="text" name="start_time" class="form-control" data-picker="time" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('End Time') }}</label>
                                <input type="text" name="end_time" class="form-control" data-picker="time">
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Type') }}</label>
                                <select name="event_type" class="form-select" required>
                                    @foreach(['pool','pool_kids','pool_pn1','pool_pn23','apnea','fosse','quarry','long_trip','theory','social'] as $t)
                                        <option value="{{ $t }}">{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Title') }}</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-8">
                                <label class="form-label">{{ __('Location') }}</label>
                                <input type="text" name="location" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Color') }}</label>
                                <input type="color" name="color_hex" class="form-control form-control-color w-100" value="#0077be">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Description') }}</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Max Participants') }}</label>
                                <input type="number" name="max_participants" class="form-control" min="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Opens (days before)') }}</label>
                                <input type="number" name="registration_opens_days_before" class="form-control" min="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Closes (days before)') }}</label>
                                <input type="number" name="registration_closes_days_before" class="form-control" min="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('WhatsApp Group URL') }}</label>
                            <input type="url" name="whatsapp_group_url" class="form-control" placeholder="https://chat.whatsapp.com/...">
                        </div>
                        <hr>
                        <div class="form-check">
                            <input type="hidden" name="propagate" value="0">
                            <input type="checkbox" name="propagate" value="1" class="form-check-input" id="propagateCheck" checked>
                            <label class="form-check-label" for="propagateCheck">
                                <strong>{{ __('Propagate changes to future events') }}</strong>
                                <br><small class="text-muted">{{ __('Updates all upcoming events generated from this pattern in the current season.') }}</small>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Save & Apply') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    const csrf = '{{ csrf_token() }}';
    const headers = {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json','X-Requested-With':'XMLHttpRequest'};
    const days = [@foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d)'{{ __($d) }}',@endforeach];

    // AJAX add pattern
    document.getElementById('patternForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        const data = Object.fromEntries(fd);
        const res = await fetch('{{ route("admin.seasons.pattern.store", $season) }}', {method:'POST', headers, body:JSON.stringify(data)});
        if (res.ok) {
            const p = await res.json();
            const div = document.createElement('div');
            div.className = 'd-flex justify-content-between align-items-center border-bottom py-2';
            div.dataset.id = p.id;
            div.innerHTML = `<div><span class="badge" style="background:${p.color_hex||'#6c757d'}">${p.event_type}</span> <strong>${days[p.day_of_week]}</strong> ${p.start_time}${p.end_time?'—'+p.end_time:''} — ${p.title} ${p.location?'<small class="text-muted">('+p.location+')</small>':''}</div><div class="d-flex gap-1"><button class="btn btn-sm btn-outline-primary btn-edit-pattern" data-url="/admin/seasons/patterns/${p.id}" data-pattern='${JSON.stringify(p)}'>{{ __('Edit') }}</button><button class="btn btn-sm btn-outline-danger btn-del-pattern" data-url="${p.delete_url}">&#x2715;</button></div>`;
            document.getElementById('patternList').appendChild(div);
            this.reset();
        }
    });

    // AJAX add holiday
    document.getElementById('holidayForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        const data = Object.fromEntries(fd);
        const res = await fetch('{{ route("admin.seasons.holiday.store", $season) }}', {method:'POST', headers, body:JSON.stringify(data)});
        if (res.ok) {
            const h = await res.json();
            const div = document.createElement('div');
            div.className = 'd-flex justify-content-between align-items-center border-bottom py-2';
            div.dataset.id = h.id;
            div.innerHTML = `<div>${h.name} <small class="text-muted">${h.start_date} — ${h.end_date}</small> ${h.is_adhoc?'<span class="badge bg-secondary">Ad-hoc</span>':''}</div><button class="btn btn-sm btn-outline-danger btn-del-holiday" data-url="${h.delete_url}">&#x2715;</button>`;
            document.getElementById('holidayList').appendChild(div);
            this.reset();
            document.getElementById('holStart').value = '';
            document.getElementById('holEnd').value = '';
        }
    });

    // AJAX delete (delegated)
    document.addEventListener('click', async function(e) {
        const btn = e.target.closest('.btn-del-pattern,.btn-del-holiday');
        if (!btn) return;
        if (!await new Promise(r=>{dcConfirm('{{ __("Delete?") }}','{{ __("Delete") }}','danger',r)})) return;
        const res = await fetch(btn.dataset.url, {method:'DELETE', headers});
        if (res.ok) btn.closest('[data-id]').remove();
    });

    // Edit pattern modal
    const editModal = new bootstrap.Modal(document.getElementById('editPatternModal'));
    const editForm = document.getElementById('editPatternForm');
    let editUrl = '';

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-edit-pattern');
        if (!btn) return;
        editUrl = btn.dataset.url;
        const p = JSON.parse(btn.dataset.pattern);

        // Populate form fields
        editForm.querySelector('[name="day_of_week"]').value = p.day_of_week ?? 0;
        editForm.querySelector('[name="event_type"]').value = p.event_type ?? 'pool';
        editForm.querySelector('[name="title"]').value = p.title ?? '';
        editForm.querySelector('[name="location"]').value = p.location ?? '';
        editForm.querySelector('[name="description"]').value = p.description ?? '';
        editForm.querySelector('[name="max_participants"]').value = p.max_participants ?? '';
        editForm.querySelector('[name="registration_opens_days_before"]').value = p.registration_opens_days_before ?? '';
        editForm.querySelector('[name="registration_closes_days_before"]').value = p.registration_closes_days_before ?? '';
        editForm.querySelector('[name="color_hex"]').value = p.color_hex ?? '#0077be';
        editForm.querySelector('[name="whatsapp_group_url"]').value = p.whatsapp_group_url ?? '';

        // Set time fields — flatpickr uses the original input, not altInput
        const startTimeInput = editForm.querySelector('[name="start_time"]');
        const endTimeInput = editForm.querySelector('[name="end_time"]');
        if (startTimeInput._flatpickr) {
            startTimeInput._flatpickr.setDate(p.start_time || '', true);
        } else {
            startTimeInput.value = p.start_time ?? '';
        }
        if (endTimeInput._flatpickr) {
            endTimeInput._flatpickr.setDate(p.end_time || '', true);
        } else {
            endTimeInput.value = p.end_time ?? '';
        }

        editModal.show();
        // Re-init datepickers inside modal after it's shown
        document.getElementById('editPatternModal').addEventListener('shown.bs.modal', function handler() {
            if (typeof window.initDatepickers === 'function') window.initDatepickers(editForm);
            // Set times again after picker init
            const st = editForm.querySelector('[name="start_time"]');
            const et = editForm.querySelector('[name="end_time"]');
            if (st._flatpickr) st._flatpickr.setDate(p.start_time || '', true);
            if (et._flatpickr) et._flatpickr.setDate(p.end_time || '', true);
            document.getElementById('editPatternModal').removeEventListener('shown.bs.modal', handler);
        });
    });

    // Submit edit form
    editForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        const data = Object.fromEntries(fd);
        // Ensure propagate is sent as boolean-like
        data.propagate = fd.get('propagate') === '1' ? 1 : 0;

        const res = await fetch(editUrl, {method:'PUT', headers, body:JSON.stringify(data)});
        if (res.ok) {
            const result = await res.json();
            editModal.hide();
            let msg = '{{ __("Pattern updated.") }}';
            if (result.events_updated > 0) {
                msg += ' ' + result.events_updated + ' {{ __("future events updated.") }}';
            }
            if (typeof showToast === 'function') showToast(msg, 'success');
            // Reload to reflect changes
            setTimeout(() => location.reload(), 800);
        } else {
            if (typeof showToast === 'function') showToast('{{ __("Error saving pattern.") }}', 'danger');
        }
    });
    // Fee taper: add/remove cutoff rows
    document.getElementById('taperAdd')?.addEventListener('click', function() {
        const tpl = document.getElementById('taperRowTpl');
        document.getElementById('taperRows').appendChild(tpl.content.cloneNode(true));
    });
    document.addEventListener('click', function(e) {
        const del = e.target.closest('.taper-del');
        if (!del) return;
        del.closest('.taper-row')?.remove();
    });
    </script>
    @endpush
</x-admin-layout>
