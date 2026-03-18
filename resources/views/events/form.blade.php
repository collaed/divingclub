<x-layout :title="$event->exists ? __('Edit Event') : __('New Event')">
    <h4 class="mb-4">{{ $event->exists ? __('Edit Event') : __('New Event') }}</h4>

    <form method="POST" action="{{ $event->exists ? route('events.update', $event) : route('events.store') }}">
        @csrf
        @if($event->exists) @method('PUT') @endif

        <div class="row">
            <div class="col-md-8 mb-3">
                <label class="form-label">{{ __('Title') }} *</label>
                <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $event->title) }}" required>
                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2 mb-3">
                <label class="form-label">{{ __('Type') }} *</label>
                <select name="event_type" id="eventType" class="form-select" required>
                    @foreach(['pool','dive','training','theory','social'] as $t)
                        <option value="{{ $t }}" {{ old('event_type', $event->event_type) === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 mb-3">
                <label class="form-label">{{ __('Color') }}</label>
                <input type="color" name="color_hex" id="eventColor" class="form-control form-control-color" value="{{ old('color_hex', $event->color_hex ?? '#0077be') }}">
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label">{{ __('Date') }} *</label>
                <input type="date" name="event_date" class="form-control @error('event_date') is-invalid @enderror" value="{{ old('event_date', $event->event_date?->format('Y-m-d')) }}" required>
                @error('event_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2 mb-3">
                <label class="form-label">{{ __('Start Time') }}</label>
                <input type="time" name="event_time" class="form-control" value="{{ old('event_time', $event->event_time ? substr($event->event_time, 0, 5) : '') }}">
            </div>
            <div class="col-md-2 mb-3">
                <label class="form-label">{{ __('End Time') }}</label>
                <input type="time" name="end_time" class="form-control" value="{{ old('end_time', $event->end_time ? substr($event->end_time, 0, 5) : '') }}">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">{{ __('End Date') }}</label>
                <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $event->end_date?->format('Y-m-d')) }}">
            </div>
            <div class="col-md-2 mb-3">
                <label class="form-label">{{ __('Season') }}</label>
                <select name="season_id" class="form-select">
                    <option value="">—</option>
                    @foreach($seasons as $s)
                        <option value="{{ $s->id }}" {{ old('season_id', $event->season_id) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">{{ __('Location') }}</label>
            <input type="text" name="location" class="form-control" list="location-suggestions" value="{{ old('location', $event->location) }}" placeholder="{{ __('Address or place name (used for Google Maps link)') }}">
            <datalist id="location-suggestions">
                @foreach($locationSuggestions ?? [] as $loc)
                    <option value="{{ $loc }}">
                @endforeach
            </datalist>
        </div>

        <div class="mb-3">
            <label class="form-label">{{ __('Description') }}</label>
            <textarea name="description" class="form-control" rows="4">{{ old('description', $event->description) }}</textarea>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label">{{ __('Responsible') }}</label>
                <select name="responsible_id" class="form-select">
                    <option value="">—</option>
                    @foreach($instructors as $i)
                        <option value="{{ $i->id }}" {{ old('responsible_id', $event->responsible_id) == $i->id ? 'selected' : '' }}>{{ $i->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">{{ __('Lead Instructor') }}</label>
                <select name="instructor_id" class="form-select">
                    <option value="">—</option>
                    @foreach($instructors as $i)
                        <option value="{{ $i->id }}" {{ old('instructor_id', $event->instructor_id) == $i->id ? 'selected' : '' }}>{{ $i->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">{{ __('Assistant IDs') }}</label>
                <input type="text" name="assistant_ids" class="form-control" value="{{ old('assistant_ids', $event->assistant_ids ? implode(',', $event->assistant_ids) : '') }}" placeholder="1,2,3">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">{{ __('Permissions Expire') }}</label>
                <input type="date" name="permissions_expire_date" class="form-control" value="{{ old('permissions_expire_date', $event->permissions_expire_date?->format('Y-m-d')) }}">
            </div>
        </div>

        <div class="row">
            <div class="col-md-2 mb-3">
                <label class="form-label">{{ __('Max Participants') }}</label>
                <input type="number" name="max_participants" class="form-control" value="{{ old('max_participants', $event->max_participants) }}" min="1">
            </div>
            <div class="col-md-2 mb-3">
                <label class="form-label">{{ __('Est. Cost (€)') }}</label>
                <input type="number" name="estimated_cost" class="form-control" value="{{ old('estimated_cost', $event->estimated_cost) }}" step="0.01" min="0">
            </div>
            <div class="col-md-2 mb-3 pt-4">
                <div class="form-check"><input type="hidden" name="waiting_list_enabled" value="0"><input type="checkbox" name="waiting_list_enabled" value="1" class="form-check-input" {{ old('waiting_list_enabled', $event->waiting_list_enabled ?? true) ? 'checked' : '' }}><label class="form-check-label">{{ __('Waiting List') }}</label></div>
            </div>
            <div class="col-md-2 mb-3 pt-4">
                <div class="form-check"><input type="hidden" name="confirmation_required" value="0"><input type="checkbox" name="confirmation_required" value="1" class="form-check-input" {{ old('confirmation_required', $event->confirmation_required) ? 'checked' : '' }}><label class="form-check-label">{{ __('Confirm Req.') }}</label></div>
            </div>
            <div class="col-md-2 mb-3 pt-4">
                <div class="form-check"><input type="hidden" name="inscriptions_closed" value="0"><input type="checkbox" name="inscriptions_closed" value="1" class="form-check-input" {{ old('inscriptions_closed', $event->inscriptions_closed) ? 'checked' : '' }}><label class="form-check-label">{{ __('Closed') }}</label></div>
            </div>
        </div>

        {{-- Deposits --}}
        <h6 class="mt-3">{{ __('Deposit Schedule') }}</h6>
        <div class="row">
            @foreach([1,2,3] as $i)
                <div class="col-md-2 mb-3">
                    <label class="form-label small">{{ __('Deposit :n Date', ['n' => $i]) }}</label>
                    <input type="date" name="deposit_{{ $i }}_date" class="form-control form-control-sm" value="{{ old('deposit_'.$i.'_date', $event->{'deposit_'.$i.'_date'}?->format('Y-m-d')) }}">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label small">{{ __('Amount €') }}</label>
                    <input type="number" name="deposit_{{ $i }}_amount" class="form-control form-control-sm" value="{{ old('deposit_'.$i.'_amount', $event->{'deposit_'.$i.'_amount'}) }}" step="0.01" min="0">
                </div>
            @endforeach
        </div>

        {{-- Communication links --}}
        <h6 class="mt-3">{{ __('Communication') }}</h6>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('WhatsApp Group URL') }}</label>
                <input type="url" name="whatsapp_group_url" class="form-control" value="{{ old('whatsapp_group_url', $event->whatsapp_group_url) }}" placeholder="https://chat.whatsapp.com/...">
                <small class="text-muted">{{ __('Open WhatsApp → Group → Invite via link → Copy link') }}</small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('Dive Site') }}</label>
                <select name="dive_site_id" class="form-select">
                    <option value="">{{ __('— None —') }}</option>
                    @foreach($diveSites ?? [] as $site)
                        <option value="{{ $site->id }}" @selected(old('dive_site_id', $event->dive_site_id) == $site->id)>
                            {{ $site->name }}{{ $site->max_depth ? ' (' . $site->max_depth . 'm)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        <a href="{{ route('events.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </form>

    <script>
    const typeColors = {pool:'#0077be', dive:'#003366', training:'#28a745', theory:'#6f42c1', social:'#ffc107'};
    document.getElementById('eventType').addEventListener('change', function() {
        document.getElementById('eventColor').value = typeColors[this.value] || '#6c757d';
    });
    </script>
</x-layout>
