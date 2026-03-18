<x-layout :title="$season->name">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">{{ $season->name }} ({{ $season->year }}) @if($season->is_active) <span class="badge bg-success">{{ __('Active') }}</span> @endif</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.seasons.preview', $season) }}" class="btn btn-sm btn-outline-info">{{ __('Preview Schedule') }}</a>
            <form method="POST" action="{{ route('admin.seasons.generate', $season) }}" onsubmit="return confirm('{{ __('Generate all events from patterns?') }}')">
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
                    @foreach($season->patterns as $p)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <div>
                                <span class="badge" style="background:{{ $p->color_hex ?? '#6c757d' }}">{{ ucfirst($p->event_type) }}</span>
                                <strong>{{ $p->dayName() }}</strong> {{ $p->start_time }}{{ $p->end_time ? '—'.$p->end_time : '' }}
                                — {{ $p->title }}
                                @if($p->location) <small class="text-muted">({{ $p->location }})</small> @endif
                            </div>
                            <form method="POST" action="{{ route('admin.seasons.pattern.destroy', $p) }}" onsubmit="return confirm('Delete?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">✕</button>
                            </form>
                        </div>
                    @endforeach

                    <form method="POST" action="{{ route('admin.seasons.pattern.store', $season) }}" class="mt-3">
                        @csrf
                        <div class="row g-2">
                            <div class="col-md-3">
                                <select name="day_of_week" class="form-select form-select-sm" required>
                                    @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $i => $d)
                                        <option value="{{ $i }}">{{ __($d) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2"><input type="time" name="start_time" class="form-control form-control-sm" required></div>
                            <div class="col-md-2"><input type="time" name="end_time" class="form-control form-control-sm"></div>
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
                            <div class="col-md-2"><input type="color" name="color_hex" class="form-control form-control-sm form-control-color" value="#0077be"></div>
                            <div class="col-md-3"><button type="submit" class="btn btn-sm btn-primary w-100">{{ __('Add Pattern') }}</button></div>
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
                    @foreach($season->holidays as $h)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <div>
                                {{ $h->name }}
                                <small class="text-muted">{{ $h->start_date->format('d/m') }} — {{ $h->end_date->format('d/m/Y') }}</small>
                                @if($h->is_adhoc) <span class="badge bg-secondary">{{ __('Ad-hoc') }}</span> @endif
                            </div>
                            <form method="POST" action="{{ route('admin.seasons.holiday.destroy', $h) }}" onsubmit="return confirm('Delete?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">✕</button>
                            </form>
                        </div>
                    @endforeach

                    <form method="POST" action="{{ route('admin.seasons.holiday.store', $season) }}" class="mt-3">
                        @csrf
                        <div class="row g-2">
                            <div class="col-md-4"><input type="text" name="name" class="form-control form-control-sm" placeholder="{{ __('Holiday name') }}" required></div>
                            <div class="col-md-3"><input type="date" name="start_date" class="form-control form-control-sm" required></div>
                            <div class="col-md-3"><input type="date" name="end_date" class="form-control form-control-sm" required></div>
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
</x-layout>
