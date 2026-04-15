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
                                @if($p->registration_opens_days_before) <span class="badge bg-info">{{ __('Opens :d days before', ['d' => $p->registration_opens_days_before]) }}</span> @endif
                            </div>
                            <button class="btn btn-sm btn-outline-danger btn-del-pattern" data-url="{{ route('admin.seasons.pattern.destroy', $p) }}">✕</button>
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
                            <button class="btn btn-sm btn-outline-danger btn-del-holiday" data-url="{{ route('admin.seasons.holiday.destroy', $h) }}">✕</button>
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

    @push('scripts')
    <script>
    const csrf = '{{ csrf_token() }}';
    const headers = {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'};
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
            div.innerHTML = `<div><span class="badge" style="background:${p.color_hex||'#6c757d'}">${p.event_type}</span> <strong>${days[p.day_of_week]}</strong> ${p.start_time}${p.end_time?'—'+p.end_time:''} — ${p.title} ${p.location?'<small class="text-muted">('+p.location+')</small>':''} ${p.registration_opens_days_before?'<span class="badge bg-info">Opens '+p.registration_opens_days_before+' days before</span>':''}</div><button class="btn btn-sm btn-outline-danger btn-del-pattern" data-url="${p.delete_url}">✕</button>`;
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
            div.innerHTML = `<div>${h.name} <small class="text-muted">${h.start_date} — ${h.end_date}</small> ${h.is_adhoc?'<span class="badge bg-secondary">Ad-hoc</span>':''}</div><button class="btn btn-sm btn-outline-danger btn-del-holiday" data-url="${h.delete_url}">✕</button>`;
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
        const res = await fetch(btn.dataset.url, {method:'DELETE', headers: {...headers, 'X-CSRF-TOKEN':csrf}});
        if (res.ok) btn.closest('[data-id]').remove();
    });
    </script>
    @endpush
</x-admin-layout>
