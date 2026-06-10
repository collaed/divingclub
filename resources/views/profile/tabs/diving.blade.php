@php
    $d = $target->detail;
    $userCerts = $target->certificationLevels()->with('federation')->orderByPivot('display_priority', 'desc')->get();
    $federations = \App\Models\Federation::active()->with(['certificationLevels' => fn($q) => $q->orderBy('category')->orderBy('rank')])->orderBy('acronym')->get();
@endphp

<div class="row mb-4">
    <div class="col-md-5">
        <form method="POST" action="{{ route('profile.update.diving') }}">
            @csrf
            <input type="hidden" name="tab" value="diving">
            <input type="hidden" name="target_user_id" value="{{ $target->id }}">
            <div class="mb-3">
                <label class="form-label">{{ __('Dive Count') }}</label>
                <input type="number" name="dive_count" class="form-control @error('dive_count') is-invalid @enderror" value="{{ old('dive_count', $d?->dive_count) }}" min="0">
                @error('dive_count') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            @php
                $lastDive = $target->eventRegistrations()
                    ->whereHas('event', fn($q) => $q->where('event_type', 'dive')->where('event_date', '<=', now()))
                    ->join('events', 'events.id', '=', 'event_registrations.event_id')
                    ->orderByDesc('events.event_date')->value('events.event_date');
            @endphp
            @if($lastDive)
            <div class="mb-3">
                <label class="form-label">{{ __('Last Dive Date') }}</label>
                <input type="text" class="form-control" value="{{ \Carbon\Carbon::parse($lastDive)->translatedFormat('d M Y') }}" disabled>
            </div>
            @endif
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">{{ __('Air Consumption') }}</label>
                    <select name="air_consumption" class="form-select @error('air_consumption') is-invalid @enderror">
                        <option value="0.25" {{ old('air_consumption', $d?->air_consumption ?? 0.5) == 0.25 ? 'selected' : '' }}>{{ __('Low') }}</option>
                        <option value="0.5" {{ old('air_consumption', $d?->air_consumption ?? 0.5) == 0.5 ? 'selected' : '' }}>{{ __('Average') }}</option>
                        <option value="0.75" {{ old('air_consumption', $d?->air_consumption ?? 0.5) == 0.75 ? 'selected' : '' }}>{{ __('High') }}</option>
                        <option value="1.0" {{ old('air_consumption', $d?->air_consumption ?? 0.5) == 1.0 ? 'selected' : '' }}>{{ __('Very High') }}</option>
                    </select>
                    @error('air_consumption') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">{{ __('Comfort Level') }}</label>
                    <select name="ease_level" class="form-select @error('ease_level') is-invalid @enderror">
                        <option value="0.25" {{ old('ease_level', $d?->ease_level ?? 0.5) == 0.25 ? 'selected' : '' }}>{{ __('Beginner') }}</option>
                        <option value="0.5" {{ old('ease_level', $d?->ease_level ?? 0.5) == 0.5 ? 'selected' : '' }}>{{ __('Comfortable') }}</option>
                        <option value="0.75" {{ old('ease_level', $d?->ease_level ?? 0.5) == 0.75 ? 'selected' : '' }}>{{ __('Experienced') }}</option>
                        <option value="1.0" {{ old('ease_level', $d?->ease_level ?? 0.5) == 1.0 ? 'selected' : '' }}>{{ __('Expert') }}</option>
                    </select>
                    @error('ease_level') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">{{ __('Primary Dive Interest') }}</label>
                    <select name="primary_intent" class="form-select @error('primary_intent') is-invalid @enderror">
                        @foreach(['exploration' => __('Exploration'), 'photography' => __('Photography'), 'training' => __('Training'), 'deep' => __('Deep Diving'), 'wreck' => __('Wreck'), 'night' => __('Night Dive'), 'drift' => __('Drift Dive')] as $val => $label)
                            <option value="{{ $val }}" {{ old('primary_intent', $d?->primary_intent ?? 'exploration') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('primary_intent') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3 d-flex align-items-end">
                    <div class="form-check">
                        <input type="hidden" name="is_photographer" value="0">
                        <input type="checkbox" name="is_photographer" value="1" class="form-check-input" id="isPhotographer" {{ old('is_photographer', $d?->is_photographer) ? 'checked' : '' }}>
                        <label class="form-check-label" for="isPhotographer">{{ __('I do underwater photography') }}</label>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Legacy Certification Level') }}</label>
                <input type="text" name="certification_level" class="form-control @error('certification_level') is-invalid @enderror" value="{{ old('certification_level', $d?->certification_level) }}" placeholder="{{ __('e.g. N2 (from old system)') }}">
                @error('certification_level') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <small class="text-muted">{{ __('Use the table on the right for structured entries.') }}</small>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Apnea Level') }}</label>
                <input type="text" name="apnea_level" list="apnea-levels-list" class="form-control @error('apnea_level') is-invalid @enderror" value="{{ old('apnea_level', $d?->apnea_level) }}" placeholder="{{ __('Select or type…') }}">
                <datalist id="apnea-levels-list">
                    <option value="Apnéiste / Indoor Freediver 1★ CMAS">
                    <option value="Apnéiste confirmé / Indoor Freediver 2★ CMAS">
                    <option value="Apnéiste en eau libre / Outdoor Freediver 1★ CMAS">
                    <option value="Apnéiste confirmé en eau libre / Outdoor Freediver 2★ CMAS">
                    <option value="Apnéiste expert en eau libre / Outdoor Freediver 3★ CMAS">
                    <option value="Poulpe – Apnée jeunes N2">
                    <option value="Dauphin – Apnée jeunes N3">
                    <option value="Initiateur Apnée">
                    <option value="MEF1">
                </datalist>
                @error('apnea_level') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Training Enrollments') }}</label>
                <input type="text" name="training_enrollments" class="form-control @error('training_enrollments') is-invalid @enderror" value="{{ old('training_enrollments', $d?->training_enrollments ? implode(', ', $d->training_enrollments) : '') }}" placeholder="N4 theory, Initiateur, ...">
                @error('training_enrollments') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </form>
    </div>

    <div class="col-md-7">
        <h6>{{ __('My Certifications') }}</h6>
        @if($userCerts->count())
            <table class="table table-sm">
                <thead><tr><th>{{ __('Federation') }}</th><th>{{ __('Level') }}</th><th>{{ __('Date Obtained') }}</th><th>★</th><th></th></tr></thead>
                <tbody>
                @foreach($userCerts as $cert)
                    <tr>
                        <td><span class="badge bg-secondary">{{ $cert->federation->acronym }}</span></td>
                        <td><strong>{{ $cert->code }}</strong> <small class="text-muted d-none d-lg-inline">{{ $cert->name }}</small></td>
                        <td>
                            <form method="POST" action="{{ route('profile.cert.update', $cert->id) }}" class="d-inline">
                                @csrf @method('PUT')
                                <input type="date" name="obtained_date" class="form-control form-control-sm d-inline-block" style="width:140px"
                                       value="{{ $cert->pivot->obtained_date ? \Carbon\Carbon::parse($cert->pivot->obtained_date)->format('Y-m-d') : '' }}" onchange="this.form.submit()">
                            </form>
                        </td>
                        <td>
                            @if($cert->pivot->is_primary)
                                <span class="badge bg-success">★</span>
                            @else
                                <form method="POST" action="{{ route('profile.cert.primary', $cert->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary py-0 px-1">{{ __('Set') }}</button></form>
                            @endif
                        </td>
                        <td><form method="POST" action="{{ route('profile.cert.remove', $cert->id) }}" class="d-inline" data-confirm="{{ __('Remove this certification?') }}" data-confirm-style="danger" data-confirm-btn="{{ __('Remove') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0 px-1">✕</button></form></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="text-muted small">{{ __('No certifications recorded yet.') }}</p>
        @endif

        <h6 class="mt-3">{{ __('Add Certification') }}</h6>
        {{-- Federation filter --}}
        <div class="mb-2 d-flex flex-wrap gap-1" id="fedFilter">
            @foreach($federations as $fed)
                <label class="btn btn-sm btn-outline-primary py-0 px-2{{ $fed->certificationLevels->isEmpty() ? ' disabled' : '' }}">
                    <input type="checkbox" class="d-none fed-check" value="{{ $fed->id }}" checked autocomplete="off"> {{ $fed->acronym }}
                </label>
            @endforeach
        </div>
        <form method="POST" action="{{ route('profile.cert.add') }}">
            @csrf
            <div class="row g-2">
                <div class="col-md-7">
                    <select name="certification_level_id" id="certSelect" class="form-select form-select-sm" required>
                        <option value="">{{ __('Select certification...') }}</option>
                        @foreach($federations as $fed)
                            <optgroup label="{{ $fed->acronym }} — {{ $fed->full_name }}" data-fed="{{ $fed->id }}">
                                @foreach($fed->certificationLevels as $cl)
                                    <option value="{{ $cl->id }}">{{ $cl->code }} — {{ $cl->name }} ({{ $cl->category }})</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><input type="date" name="obtained_date" class="form-control form-control-sm"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary w-100">{{ __('Add') }}</button></div>
            </div>
        </form>
    </div>
</div>

<hr>
<h6>{{ __('Document Scans') }}</h6>
@foreach($target->documents->where('category', 'certification') as $doc)
    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
        <div>
            <a href="{{ route('profile.document.download', $doc) }}">{{ $doc->original_filename }}</a>
            <small class="text-muted ms-2">{{ $doc->date_established?->format('d/m/Y') }}</small>
        </div>
    </div>
@endforeach

<form method="POST" action="{{ route('profile.document.upload') }}" enctype="multipart/form-data" class="mt-3">
    @csrf
    <input type="hidden" name="category" value="certification">
    @if($target->id !== auth()->id())
        <input type="hidden" name="target_user_id" value="{{ $target->id }}">
    @endif
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label">{{ __('Upload Scan') }} (PDF/JPG/PNG, max 10MB)</label>
            <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png" required>
            @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3"><label class="form-label">{{ __('Date') }}</label><input type="date" name="date_established" class="form-control @error('date_established') is-invalid @enderror">
            @error('date_established') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
        <div class="col-md-2"><button type="submit" class="btn btn-primary">{{ __('Upload') }}</button></div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('certSelect');
    const checks = document.querySelectorAll('.fed-check');
    const STORAGE_KEY = 'dc_fed_filter';
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');

    if (saved) {
        checks.forEach(c => { c.checked = saved.includes(c.value); });
    }

    function filterFeds() {
        const active = [...checks].filter(c => c.checked).map(c => c.value);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(active));
        select.querySelectorAll('optgroup').forEach(og => {
            og.style.display = active.includes(og.dataset.fed) ? '' : 'none';
            og.querySelectorAll('option').forEach(o => o.hidden = !active.includes(og.dataset.fed));
        });
    }
    checks.forEach(c => {
        c.closest('label').addEventListener('click', function(e) {
            e.preventDefault();
            c.checked = !c.checked;
            this.classList.toggle('active', c.checked);
            filterFeds();
        });
        c.closest('label').classList.toggle('active', c.checked);
    });
    if (saved) filterFeds();
});
</script>

@if($d?->active_instructor || $target->hasAnyRole(['instructor', 'assistant']))
<hr>
<h6>@icon('🎓') {{ __('Instructor Profile') }}</h6>
<p class="text-muted small">{{ __('This information is visible to all members and helps newcomers connect with you.') }}</p>
<form method="POST" action="{{ route('profile.update.diving') }}">
    @csrf
    <input type="hidden" name="tab" value="instructor_bio">
    <input type="hidden" name="target_user_id" value="{{ $target->id }}">
    <div class="mb-3">
        <label class="form-label">{{ __('Experience & Background') }}</label>
        <textarea name="instructor_bio" class="form-control @error('instructor_bio') is-invalid @enderror" rows="3" placeholder="{{ __('e.g. Diving since 2005, FFESSM N4 instructor, 500+ logged dives...') }}">{{ old('instructor_bio', $d?->instructor_bio) }}</textarea>
        @error('instructor_bio') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label class="form-label">{{ __('Specialties & Interests') }}</label>
        <textarea name="instructor_specialties" class="form-control @error('instructor_specialties') is-invalid @enderror" rows="2" placeholder="{{ __('e.g. Wreck diving, underwater photography, Nitrox...') }}">{{ old('instructor_specialties', $d?->instructor_specialties) }}</textarea>
        @error('instructor_specialties') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label class="form-label">{{ __('What motivates you?') }}</label>
        <textarea name="instructor_motivation" class="form-control @error('instructor_motivation') is-invalid @enderror" rows="2" placeholder="{{ __('e.g. Sharing the passion, helping beginners gain confidence underwater...') }}">{{ old('instructor_motivation', $d?->instructor_motivation) }}</textarea>
        @error('instructor_motivation') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3 form-check">
        <input type="hidden" name="show_on_public_site" value="0">
        <input type="checkbox" name="show_on_public_site" value="1" class="form-check-input" id="showOnPublicSite" {{ old('show_on_public_site', $d?->show_on_public_site ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="showOnPublicSite">{{ __('Show my profile on the public Instructors page') }}</label>
    </div>
    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
</form>
@endif
