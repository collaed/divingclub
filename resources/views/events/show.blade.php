<x-layout :title="$event->title">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('events.index') }}">{{ __('Calendar') }}</a></li><li class="breadcrumb-item active">{{ $event->title }}</li></ol></nav>

    <div class="row">
        <div class="col-lg-8">
            <div class="card dc-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge" style="background:{{ $event->typeColor() }}">{{ ucfirst($event->event_type) }}</span>
                        <span class="badge bg-{{ $event->status === 'cancelled' ? 'danger' : ($event->status === 'completed' ? 'secondary' : 'success') }}">{{ ucfirst($event->status) }}</span>
                    </div>
                    @if(auth()->check() && (auth()->user()->isBureau() || $event->instructor_id === auth()->id()))
                        <a href="{{ route('events.edit', $event) }}" class="btn btn-sm btn-outline-primary">{{ __('Edit') }}</a>
                    @endif
                </div>
                <div class="card-body">
                    <h4>{{ $event->title }}</h4>
                    <table class="table table-sm table-borderless">
                        <tr><th style="width:150px">{{ __('Date') }}</th><td>{{ $event->event_date->format('l, d F Y') }}{{ $event->end_date && !$event->end_date->eq($event->event_date) ? ' — ' . $event->end_date->format('d F Y') : '' }}</td></tr>
                        @if($event->event_time)<tr><th>{{ __('Time') }}</th><td>{{ substr($event->event_time, 0, 5) }}{{ $event->end_time ? ' — ' . substr($event->end_time, 0, 5) : '' }}</td></tr>@endif
                        @if($event->location)
                            <tr><th>{{ __('Location') }}</th><td>
                                {{ $event->location }}
                                <a href="{{ $event->mapsUrl() }}" target="_blank" class="ms-2 small">📍 {{ __('Map') }}</a>
                            </td></tr>
                        @endif
                        @if($event->instructor)<tr><th>{{ __('Instructor') }}</th><td>{{ $event->instructor->name }}</td></tr>@endif
                        @if($event->responsible)<tr><th>{{ __('Responsible') }}</th><td>{{ $event->responsible->name }}</td></tr>@endif
                        @if($event->estimated_cost)<tr><th>{{ __('Est. Cost') }}</th><td>€{{ number_format($event->estimated_cost, 2) }}</td></tr>@endif
                        <tr><th>{{ __('Participants') }}</th><td>{{ $event->confirmedRegistrations->count() }}{{ $event->max_participants ? ' / ' . $event->max_participants : '' }}</td></tr>
                    </table>

                    @if($event->description)
                        <hr>
                        <div>{!! nl2br(e($event->description)) !!}</div>
                    @endif

                    {{-- Dive Site info --}}
                    @if($event->diveSite)
                        <hr>
                        <h5>🤿 {{ __('Dive Site') }}: {{ $event->diveSite->name }}</h5>
                        <div class="row">
                            @if($event->diveSite->image_path)
                                <div class="col-md-4 mb-2">
                                    <img src="{{ asset('storage/' . $event->diveSite->image_path) }}" class="img-fluid rounded" alt="{{ $event->diveSite->name }}">
                                </div>
                            @endif
                            <div class="{{ $event->diveSite->image_path ? 'col-md-4' : 'col-md-8' }}">
                                <table class="table table-sm table-borderless mb-0">
                                    @if($event->diveSite->water_type)<tr><th style="width:120px">{{ __('Type') }}</th><td>{{ ucfirst($event->diveSite->water_type) }}</td></tr>@endif
                                    @if($event->diveSite->max_depth)<tr><th>{{ __('Max Depth') }}</th><td>{{ $event->diveSite->max_depth }}m</td></tr>@endif
                                    @if($event->diveSite->country)<tr><th>{{ __('Location') }}</th><td>{{ $event->diveSite->region }}{{ $event->diveSite->region && $event->diveSite->country ? ', ' : '' }}{{ $event->diveSite->country }}</td></tr>@endif
                                    @if($event->diveSite->entry_fee)<tr><th>{{ __('Entry Fee') }}</th><td>€{{ number_format($event->diveSite->entry_fee, 2) }} <span class="text-muted small">({{ __('indicative') }})</span></td></tr>@endif
                                </table>
                                @if($event->diveSite->conditions)<p class="small mb-1"><strong>{{ __('Conditions') }}:</strong> {{ $event->diveSite->conditions }}</p>@endif
                                @if($event->diveSite->marine_life)<p class="small mb-1"><strong>{{ __('Marine Life') }}:</strong> {{ $event->diveSite->marine_life }}</p>@endif
                                @if($event->diveSite->safety_notes)<p class="small mb-1 text-danger"><strong>{{ __('Safety') }}:</strong> {{ $event->diveSite->safety_notes }}</p>@endif
                                @if($event->diveSite->access_notes)<p class="small mb-1"><strong>{{ __('Access') }}:</strong> {{ $event->diveSite->access_notes }}</p>@endif
                                @if($event->diveSite->facilities)<p class="small mb-1"><strong>{{ __('Facilities') }}:</strong> {{ $event->diveSite->facilities }}</p>@endif
                                @if($event->diveSite->food_options)<p class="small mb-1"><strong>🍽️ {{ __('Food & Drink') }}:</strong> {{ $event->diveSite->food_options }}</p>@endif
                                @if($event->diveSite->nearest_hospital)<p class="small mb-0 text-danger"><strong>🏥 {{ __('Nearest Hospital') }}:</strong> {{ $event->diveSite->nearest_hospital }}</p>@endif
                                <div class="mt-1 d-flex gap-2 flex-wrap">
                                    @if($event->diveSite->website_url)<a href="{{ $event->diveSite->website_url }}" target="_blank" class="btn btn-sm btn-outline-secondary">🌐 {{ __('Website') }}</a>@endif
                                    @if($event->diveSite->booking_url)<a href="{{ $event->diveSite->booking_url }}" target="_blank" class="btn btn-sm btn-outline-primary">📅 {{ __('Book') }}</a>@endif
                                    @if($event->diveSite->site_plan_path)<a href="{{ asset('storage/' . $event->diveSite->site_plan_path) }}" target="_blank" class="btn btn-sm btn-outline-info">📄 {{ __('Site Plan') }}</a>@endif
                                </div>
                                {{-- Safety documents --}}
                                @if($event->diveSite->safety_docs_folder)
                                    @php $safetyDocs = \App\Models\LibraryFile::where('folder', $event->diveSite->safety_docs_folder)->where('is_public', true)->get(); @endphp
                                    @if($safetyDocs->count())
                                        <div class="mt-2">
                                            <strong class="small">📋 {{ __('Safety Documents') }}:</strong>
                                            @foreach($safetyDocs as $doc)
                                                <a href="{{ route('documents.download', $doc) }}" class="btn btn-sm btn-outline-danger ms-1">{{ $doc->original_name }}</a>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif
                            </div>
                            {{-- Map + Weather --}}
                            @if($event->diveSite->latitude && $event->diveSite->longitude)
                                <div class="col-md-4 mb-2">
                                    <a href="{{ $event->diveSite->mapsUrl() }}" target="_blank" title="{{ __('Click for exact location') }}">
                                        @if($event->diveSite->map_image_path)
                                            <img src="{{ asset('storage/' . $event->diveSite->map_image_path) }}" class="img-fluid rounded border" alt="{{ __('Map') }}">
                                        @endif
                                        <div class="text-center mt-1">
                                            <span class="btn btn-sm btn-outline-primary">📍 {{ __('View on Map') }}</span>
                                        </div>
                                    </a>
                                    {{-- Weather widget --}}
                                    <div class="card mt-2" id="weather-widget">
                                        <div class="card-body py-2 text-center small">
                                            <strong>🌤️ {{ __('Weather Forecast') }}</strong>
                                            <div id="weather-data" class="mt-1 text-muted">{{ __('Loading…') }}</div>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                fetch('https://api.open-meteo.com/v1/forecast?latitude={{ $event->diveSite->latitude }}&longitude={{ $event->diveSite->longitude }}&daily=temperature_2m_max,temperature_2m_min,precipitation_sum,windspeed_10m_max,weathercode&timezone=Europe/Luxembourg&forecast_days=7')
                                    .then(r => r.json()).then(d => {
                                        if (!d.daily) return;
                                        const icons = {0:'☀️',1:'🌤️',2:'⛅',3:'☁️',45:'🌫️',48:'🌫️',51:'🌦️',53:'🌧️',55:'🌧️',61:'🌧️',63:'🌧️',65:'🌧️',71:'🌨️',73:'🌨️',75:'🌨️',80:'🌦️',81:'🌧️',82:'🌧️',95:'⛈️',96:'⛈️',99:'⛈️'};
                                        let html = '<table class="table table-sm mb-0" style="font-size:0.75rem"><tbody>';
                                        for (let i = 0; i < Math.min(5, d.daily.time.length); i++) {
                                            const dt = new Date(d.daily.time[i]);
                                            const day = dt.toLocaleDateString('{{ app()->getLocale() }}', {weekday:'short',day:'numeric'});
                                            const wc = d.daily.weathercode[i];
                                            html += '<tr><td>' + day + '</td><td>' + (icons[wc]||'🌡️') + '</td><td>' + d.daily.temperature_2m_min[i] + '—' + d.daily.temperature_2m_max[i] + '°C</td><td>💨' + d.daily.windspeed_10m_max[i] + 'km/h</td></tr>';
                                        }
                                        html += '</tbody></table>';
                                        document.getElementById('weather-data').innerHTML = html;
                                    }).catch(() => { document.getElementById('weather-data').textContent = '{{ __("Weather unavailable") }}'; });
                                </script>
                            @endif
                        </div>
                    @endif

                    {{-- Google Maps embed if API key available --}}
                    @if($event->location && config('club.google_maps_key'))
                        <div class="mt-3 ratio ratio-16x9">
                            <iframe src="{{ $event->mapsUrl() }}" allowfullscreen loading="lazy" style="border:0; border-radius:0.5rem;"></iframe>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Deposits --}}
            @if($event->deposit_1_amount || $event->deposit_2_amount || $event->deposit_3_amount)
                <div class="card dc-card mb-4">
                    <div class="card-header">{{ __('Deposit Schedule') }}</div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <thead><tr><th>#</th><th>{{ __('Date') }}</th><th>{{ __('Amount') }}</th></tr></thead>
                            <tbody>
                            @foreach([1,2,3] as $i)
                                @if($event->{'deposit_'.$i.'_amount'})
                                    <tr><td>{{ $i }}</td><td>{{ $event->{'deposit_'.$i.'_date'}?->format('d/m/Y') ?? '—' }}</td><td>€{{ number_format($event->{'deposit_'.$i.'_amount'}, 2) }}</td></tr>
                                @endif
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            {{-- Registration --}}
            @auth
                <div class="card dc-card mb-4">
                    <div class="card-header">{{ __('Registration') }}</div>
                    <div class="card-body">
                        @if($userReg && $userReg->status !== 'cancelled')
                            <p>{{ __('Your status') }}: <span class="badge bg-{{ $userReg->status === 'confirmed' ? 'success' : 'warning text-dark' }}">{{ ucfirst($userReg->status) }}</span></p>
                            @if($userReg->status === 'waiting')
                                <p class="small text-muted">{{ __('Position') }}: #{{ $userReg->waiting_list_position }}</p>
                            @endif
                            <form method="POST" action="{{ route('events.cancel-registration', $event) }}" onsubmit="return confirm('{{ __('Cancel registration?') }}')">
                                @csrf
                                <button class="btn btn-outline-danger btn-sm">{{ __('Cancel Registration') }}</button>
                            </form>
                        @elseif($event->isRegistrationOpen())
                            @php $myMed = app(\App\Services\MedicalComplianceService::class)->getStatus(auth()->user()); @endphp
                            @if(in_array($event->event_type, ['pool','dive','training']) && $myMed['status'] !== 'compliant')
                                <div class="alert alert-warning small py-2 mb-2">
                                    ⚠️ {{ __('A valid medical certificate is required for this event.') }}
                                    <a href="{{ route('profile.show', ['tab' => 'medical']) }}">{{ __('Upload now') }}</a>
                                </div>
                            @endif
                            <form method="POST" action="{{ route('events.register', $event) }}">
                                @csrf
                                <button class="btn btn-primary w-100">
                                    {{ $event->isFull() ? __('Join Waiting List') : __('Register') }}
                                </button>
                            </form>
                            @if($event->isFull())
                                <p class="small text-muted mt-2">{{ __('Event is full. You will be placed on the waiting list.') }}</p>
                            @endif
                        @else
                            <p class="text-muted">{{ __('Registration is closed.') }}</p>
                            @if($event->inscription_open_at && $event->inscription_open_at->isFuture())
                                <p class="small">{{ __('Opens') }}: {{ $event->inscription_open_at->format('d/m/Y H:i') }}</p>
                            @endif
                        @endif
                    </div>
                </div>
            @endauth

            {{-- Participant list (visible to bureau/instructor) --}}
            @if(auth()->check() && (auth()->user()->isBureau() || $event->instructor_id === auth()->id() || in_array(auth()->id(), $event->assistant_ids ?? [])))
                <div class="card dc-card mb-4">
                    <div class="card-header">{{ __('Participants') }}</div>
                    <div class="list-group list-group-flush">
                        @foreach($event->registrations->where('status', 'confirmed') as $reg)
                            @php $regMed = app(\App\Services\MedicalComplianceService::class)->getStatus($reg->user); @endphp
                            <div class="list-group-item d-flex justify-content-between small">
                                <span>{{ $reg->user->name }} <span class="badge bg-{{ $regMed['badge'] }}" style="font-size:0.6rem;">{{ __($regMed['label']) }}</span></span>
                                <span class="badge bg-success">{{ __('Confirmed') }}</span>
                            </div>
                        @endforeach
                        @foreach($event->registrations->where('status', 'waiting')->sortBy('waiting_list_position') as $reg)
                            <div class="list-group-item d-flex justify-content-between small">
                                <span>{{ $reg->user->name }}</span>
                                <span class="badge bg-warning text-dark">#{{ $reg->waiting_list_position }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Payment due for this event --}}
            @auth
                @php $userPayment = \App\Models\PaymentExpected::where('event_id', $event->id)->where('user_id', auth()->id())->first(); @endphp
                @if($userPayment)
                    <div class="card dc-card mb-4 {{ $userPayment->status === 'paid' ? 'border-success' : 'border-warning' }}">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>💳 {{ __('Payment') }}</span>
                            <span class="badge bg-{{ $userPayment->status === 'paid' ? 'success' : 'warning text-dark' }}">{{ ucfirst($userPayment->status) }}</span>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-2">
                                <tr><th class="text-muted" style="width:120px">{{ __('Amount') }}</th><td class="fw-bold">€{{ number_format($userPayment->amount_due, 2) }}</td></tr>
                                <tr><th class="text-muted">{{ __('Communication') }}</th><td><code class="user-select-all">{{ $userPayment->communication }}</code></td></tr>
                                @if($userPayment->components)
                                    @foreach($userPayment->components as $comp)
                                        <tr><td class="small text-muted" colspan="2">• {{ $comp['label'] ?? $comp['type'] ?? '' }}: €{{ number_format($comp['amount'] ?? 0, 2) }}</td></tr>
                                    @endforeach
                                @endif
                                @if($userPayment->status === 'paid')
                                    <tr><th class="text-muted">{{ __('Paid') }}</th><td>{{ $userPayment->paid_at?->format('d/m/Y') }}</td></tr>
                                @endif
                            </table>
                            @if($userPayment->status !== 'paid')
                                @php $clubIban = \App\Models\ThemeSetting::get('club_iban'); @endphp
                                @if($clubIban)
                                    <div class="small text-muted mb-2">
                                        IBAN: <code>{{ $clubIban }}</code>
                                    </div>
                                    <div class="text-center">
                                        <img src="{{ route('qr.sepa', $userPayment) }}" alt="SEPA QR" class="border rounded p-1" style="max-width:180px">
                                        <p class="small text-muted mt-1 mb-0">{{ __('Scan to pay with your banking app') }}</p>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                @endif
            @endauth

            {{-- Event email --}}
            @if($event->participant_email)

            {{-- Dive Groups link --}}
            @if(in_array($event->event_type, ['dive', 'training']))
                <div class="card dc-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>🤿 {{ __('Dive Groups') }}</span>
                        <span class="badge bg-secondary">{{ $event->diveGroups->count() }}</span>
                    </div>
                    <div class="card-body">
                        @if($event->diveGroups->count())
                            @foreach($event->diveGroups as $group)
                                <div class="mb-1">
                                    <strong>{{ $group->name }}</strong>
                                    <span class="badge bg-{{ match($group->dive_mode) { 'supervised' => 'primary', 'autonomous' => 'success', 'training' => 'warning text-dark', 'certification' => 'danger', default => 'secondary' } }}">{{ ucfirst($group->dive_mode) }}</span>
                                    <span class="small text-muted">({{ $group->members->count() }} {{ __('members') }})</span>
                                </div>
                            @endforeach
                        @else
                            <p class="small text-muted mb-0">{{ __('No groups planned yet.') }}</p>
                        @endif
                        <a href="{{ route('events.dive-groups', $event) }}" class="btn btn-sm btn-outline-primary mt-2 w-100">{{ __('Open Group Planner') }}</a>
                    </div>
                </div>
            @endif

                <div class="card dc-card mb-4">
                    <div class="card-body small">
                        <strong>{{ __('Event Email') }}:</strong><br>
                        <a href="mailto:{{ $event->participant_email }}">{{ $event->participant_email }}</a>
                        <p class="text-muted mt-1 mb-0">{{ __('Emails to this address are forwarded to all participants.') }}</p>
                    </div>
                </div>
            @endif

            {{-- WhatsApp group --}}
            @if($event->whatsapp_group_url)
                <div class="card dc-card mb-4">
                    <div class="card-body small">
                        <a href="{{ $event->whatsapp_group_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-success">
                            💬 {{ __('Join WhatsApp Group') }}
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Photo Gallery / Slideshow --}}
    @php $photos = $event->photos()->where('approved', true)->orderByDesc('quality_score')->get(); @endphp
    @if($photos->count() || (auth()->check() && $event->registrations()->where('user_id', auth()->id())->where('status', 'confirmed')->exists()))
        <div class="card dc-card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>📸 {{ __('Event Photos') }} @if($photos->count())<span class="badge bg-secondary">{{ $photos->count() }}</span>@endif</span>
                @if($photos->count() > 1)
                    <button class="btn btn-sm btn-outline-primary" onclick="startSlideshow()">▶ {{ __('Slideshow') }}</button>
                @endif
            </div>
            <div class="card-body">
                @if($photos->count())
                    <div class="row g-2">
                        @foreach($photos as $photo)
                            <div class="col-6 col-md-3 position-relative">
                                <img src="{{ asset('storage/' . $photo->path) }}" alt="{{ $photo->caption }}" class="img-fluid rounded slideshow-img cursor-pointer" style="height:150px; width:100%; object-fit:cover;" onclick="openSlide({{ $loop->index }})">
                                <span class="position-absolute top-0 end-0 badge bg-dark bg-opacity-50 m-1" title="{{ __('Quality') }}">{{ $photo->quality_score }}%</span>
                                @if(auth()->check() && (auth()->user()->isBureau() || $photo->uploaded_by === auth()->id()))
                                    <form method="POST" action="{{ route('events.photo.delete', [$event, $photo]) }}" class="position-absolute bottom-0 end-0 m-1" onsubmit="return confirm('{{ __('Delete photo?') }}')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger py-0 px-1" style="font-size:0.7rem">✕</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Upload form (only for confirmed participants with GDPR consent) --}}
                @auth
                    @php
                        $isParticipant = $event->registrations()->where('user_id', auth()->id())->where('status', 'confirmed')->exists();
                        $hasConsent = \App\Models\GdprConsent::where('user_id', auth()->id())->where('consent_type', 'photo_publication')->where('granted', true)->exists();
                    @endphp
                    @if($isParticipant)
                        @if($hasConsent)
                            <form method="POST" action="{{ route('events.photo.upload', $event) }}" enctype="multipart/form-data" class="mt-3">
                                @csrf
                                <div class="row g-2">
                                    <div class="col">
                                        <input type="file" name="photos[]" class="form-control form-control-sm" accept="image/*" multiple required>
                                    </div>
                                    <div class="col-auto">
                                        <input type="text" name="caption" class="form-control form-control-sm" placeholder="{{ __('Caption (optional)') }}">
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-sm btn-primary">{{ __('Upload') }}</button>
                                    </div>
                                </div>
                                <div class="form-check mt-1">
                                    <input type="checkbox" name="gdpr_consent" value="1" class="form-check-input" id="gdprPhotoConsent" required>
                                    <label class="form-check-label small" for="gdprPhotoConsent">{{ __('I consent to these photos being shared on the club\'s social media channels') }}</label>
                                </div>
                                <small class="text-muted">{{ __('Max 10MB per photo. Best photos appear first.') }}</small>
                            </form>
                        @else
                            <div class="alert alert-info small mt-3 mb-0 py-2">
                                📷 {{ __('To upload photos, please grant') }} <a href="{{ route('gdpr.consents') }}">{{ __('photo publication consent') }}</a>.
                            </div>
                        @endif
                    @endif
                @endauth
            </div>
        </div>

        {{-- Slideshow modal --}}
        @if($photos->count() > 1)
        <div class="modal fade" id="slideshowModal" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content bg-dark">
                    <div class="modal-body p-0 text-center position-relative">
                        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 z-3" data-bs-dismiss="modal"></button>
                        <img id="slideImg" src="" class="img-fluid" style="max-height:80vh; object-fit:contain;">
                        <div class="position-absolute start-0 top-50 translate-middle-y">
                            <button class="btn btn-dark btn-lg opacity-75" onclick="prevSlide()">‹</button>
                        </div>
                        <div class="position-absolute end-0 top-50 translate-middle-y">
                            <button class="btn btn-dark btn-lg opacity-75" onclick="nextSlide()">›</button>
                        </div>
                        <div class="text-white small p-2" id="slideCaption"></div>
                    </div>
                </div>
            </div>
        </div>
        <script>
        const slides = {!! json_encode($photos->map(fn($p) => ['url' => asset('storage/' . $p->path), 'caption' => $p->caption ?? ''])->values()) !!};
        let slideIdx = 0, slideTimer = null;
        const modal = () => new bootstrap.Modal(document.getElementById('slideshowModal'));
        function showSlide(i) { slideIdx = (i + slides.length) % slides.length; document.getElementById('slideImg').src = slides[slideIdx].url; document.getElementById('slideCaption').textContent = (slideIdx+1)+'/'+slides.length+(slides[slideIdx].caption ? ' — '+slides[slideIdx].caption : ''); }
        function openSlide(i) { showSlide(i); modal().show(); }
        function nextSlide() { showSlide(slideIdx + 1); }
        function prevSlide() { showSlide(slideIdx - 1); }
        function startSlideshow() { openSlide(0); slideTimer = setInterval(nextSlide, 4000); }
        document.getElementById('slideshowModal')?.addEventListener('hidden.bs.modal', () => { clearInterval(slideTimer); slideTimer = null; });
        document.addEventListener('keydown', e => { if(document.getElementById('slideshowModal')?.classList.contains('show')) { if(e.key==='ArrowRight') nextSlide(); if(e.key==='ArrowLeft') prevSlide(); }});
        </script>
        @endif
    @endif
</x-layout>
