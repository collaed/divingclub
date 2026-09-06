<x-admin-layout :title="__('System Settings')">
    <h4 class="mb-4">{{ __('System Settings') }}</h4>

    {{-- Tab navigation --}}
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-club" type="button">@icon('🏢') {{ __('Club & Finance') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-rules" type="button">@icon('📋') {{ __('Rules & Compliance') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-appearance" type="button">@icon('🎨') {{ __('Appearance') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-technical" type="button">@icon('⚙️') {{ __('Technical') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-languages" type="button">@icon('🌍') {{ __('Languages') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-license" type="button">@icon('🔑') {{ __('License') }}</button></li>
    </ul>

    <div class="tab-content">

    {{-- TAB 1: Club & Finance --}}
    <div class="tab-pane fade show active" id="tab-club">
    <div class="accordion" id="clubAccordion">

        {{-- Federations --}}
        <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" aria-expanded="false" data-bs-target="#fedSection">{{ __('Federations') }}</button></h2>
            <div id="fedSection" class="accordion-collapse collapse show" data-bs-parent="#clubAccordion">
                <div class="accordion-body">
                    <table class="table table-sm">
                        <thead><tr><th>{{ __('Acronym') }}</th><th>{{ __('Full Name') }}</th><th>{{ __('Visibility') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach($federations as $fed)
                            <tr>
                                <form method="POST" action="{{ route('admin.settings.federation.update', $fed) }}">
                                    @csrf @method('PUT')
                                    <td><input type="text" name="acronym" class="form-control form-control-sm" value="{{ $fed->acronym }}" required></td>
                                    <td><input type="text" name="full_name" class="form-control form-control-sm" value="{{ $fed->full_name }}" required></td>
                                    <td>
                                        <select name="visibility" class="form-select form-select-sm">
                                            <option value="active" @selected($fed->visibility === 'active')>{{ __('Active') }}</option>
                                            <option value="recognized" @selected($fed->visibility === 'recognized')>{{ __('Recognized') }}</option>
                                            <option value="invisible" @selected($fed->visibility === 'invisible')>{{ __('Invisible') }}</option>
                                        </select>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Save') }}</button>
                                </form>
                                        <form method="POST" action="{{ route('admin.settings.federation.destroy', $fed) }}" class="d-inline" data-confirm="Delete?" data-confirm-style="danger" data-confirm-btn="{{ __('Confirm') }}">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">✕</button>
                                        </form>
                                    </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <form method="POST" action="{{ route('admin.settings.federation.store') }}" class="row g-2 mt-2">
                        @csrf
                        <div class="col-md-3"><input type="text" name="acronym" class="form-control form-control-sm" placeholder="{{ __('Acronym') }}" required></div>
                        <div class="col-md-6"><input type="text" name="full_name" class="form-control form-control-sm" placeholder="{{ __('Full Name') }}" required></div>
                        <div class="col-md-3"><button type="submit" class="btn btn-sm btn-primary">{{ __('Add Federation') }}</button></div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Member Statuses --}}
        <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" aria-expanded="false" data-bs-target="#statusSection">{{ __('Member Statuses') }}</button></h2>
            <div id="statusSection" class="accordion-collapse collapse" data-bs-parent="#clubAccordion">
                <div class="accordion-body">
                    <table class="table table-sm">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Slug') }}</th><th>{{ __('Description') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach($statuses as $s)
                            <tr>
                                <form method="POST" action="{{ route('admin.settings.status.update', $s) }}">
                                    @csrf @method('PUT')
                                    <td><input type="text" name="name" class="form-control form-control-sm" value="{{ $s->name }}" required></td>
                                    <td><code>{{ $s->slug }}</code></td>
                                    <td><input type="text" name="description" class="form-control form-control-sm" value="{{ $s->description }}"></td>
                                    <td><button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Save') }}</button></td>
                                </form>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <form method="POST" action="{{ route('admin.settings.status.store') }}" class="row g-2 mt-2">
                        @csrf
                        <div class="col-md-3"><input type="text" name="name" class="form-control form-control-sm" placeholder="{{ __('Name') }}" required></div>
                        <div class="col-md-3"><input type="text" name="slug" class="form-control form-control-sm" placeholder="{{ __('slug') }}" required></div>
                        <div class="col-md-3"><input type="text" name="description" class="form-control form-control-sm" placeholder="{{ __('Description') }}"></div>
                        <div class="col-md-3"><button type="submit" class="btn btn-sm btn-primary">{{ __('Add Status') }}</button></div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Membership Fees (absolute amounts per status per year) --}}
        <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" aria-expanded="false" data-bs-target="#feesSection">{{ __('Membership Fees (per Year)') }}</button></h2>
            <div id="feesSection" class="accordion-collapse collapse" data-bs-parent="#clubAccordion">
                <div class="accordion-body">
                    <p class="text-muted small">{{ __('Set the absolute membership fee per status per season year. These amounts are decided by the Bureau or the Annual General Meeting.') }}</p>
                    @if($membershipFees->count())
                    <table class="table table-sm">
                        <thead><tr><th>{{ __('Year') }}</th><th>{{ __('Status') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Label') }}</th><th>{{ __('Notes') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach($membershipFees as $mf)
                            <tr>
                                <td>{{ $mf->season_year }}</td>
                                <td><span class="badge bg-secondary">{{ $mf->status?->name }}</span></td>
                                <td>€{{ number_format($mf->amount, 2) }}</td>
                                <td>{{ $mf->label }}</td>
                                <td class="small text-muted">{{ $mf->notes }}</td>
                                <td><form method="POST" action="{{ route('admin.settings.membership-fee.destroy', $mf) }}" class="d-inline" data-confirm="Delete?" data-confirm-style="danger" data-confirm-btn="{{ __('Confirm') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">✕</button></form></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @endif
                    <form method="POST" action="{{ route('admin.settings.membership-fee.store') }}" class="row g-2 mt-2">
                        @csrf
                        <div class="col-md-1"><input type="text" name="season_year" class="form-control form-control-sm" placeholder="{{ date('Y') }}" value="{{ date('Y') }}" required></div>
                        <div class="col-md-2">
                            <select name="status_id" class="form-select form-select-sm" required>
                                @foreach($statuses as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-2"><input type="number" name="amount" class="form-control form-control-sm" placeholder="€" step="0.01" min="0" required></div>
                        <div class="col-md-2"><input type="text" name="label" class="form-control form-control-sm" placeholder="{{ __('Label (optional)') }}"></div>
                        <div class="col-md-3"><input type="text" name="notes" class="form-control form-control-sm" placeholder="{{ __('Notes (e.g. AG decision)') }}"></div>
                        <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary">{{ __('Set Fee') }}</button></div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Status Sets (eligibility base categories) --}}
        <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" aria-expanded="false" data-bs-target="#statusSetsSection">{{ __('Status Sets (Eligibility)') }}</button></h2>
            <div id="statusSetsSection" class="accordion-collapse collapse" data-bs-parent="#clubAccordion">
                <div class="accordion-body">
                    <p class="text-muted small">{{ __('A status set is a member\'s sticky base category. It defines which statuses a member may hold across seasons. Tick the statuses each set offers; mark one as the default (full membership). Changes save automatically.') }}</p>

                    @foreach(($statusSets ?? []) as $set)
                        @php $setStatusIds = $set->statuses->pluck('id')->all(); $defaultId = optional($set->statuses->firstWhere('pivot.is_default', true))->id; @endphp
                        <div class="card dc-card mb-2" data-set-id="{{ $set->id }}" data-url="{{ route('admin.settings.status-set.update', $set) }}">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>{{ $set->name }}</strong> <code class="small text-muted">{{ $set->slug }}</code>
                                    <form method="POST" action="{{ route('admin.settings.status-set.destroy', $set) }}" class="d-inline" data-confirm="{{ __('Delete this set?') }}" data-confirm-style="danger">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">✕</button></form>
                                </div>
                                <div class="row">
                                    @foreach($statuses as $s)
                                        <div class="col-md-3 col-6">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input js-set-status" data-set="{{ $set->id }}" value="{{ $s->id }}" id="set{{ $set->id }}_st{{ $s->id }}" {{ in_array($s->id, $setStatusIds) ? 'checked' : '' }}>
                                                <label class="form-check-label small" for="set{{ $set->id }}_st{{ $s->id }}">{{ $s->name }}</label>
                                                <input type="radio" name="default_set{{ $set->id }}" class="form-check-input ms-2 js-set-default" data-set="{{ $set->id }}" value="{{ $s->id }}" title="{{ __('Default (full)') }}" {{ $defaultId == $s->id ? 'checked' : '' }}>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <form method="POST" action="{{ route('admin.settings.status-set.store') }}" class="row g-2 mt-2">
                        @csrf
                        <div class="col-md-4"><input type="text" name="name" class="form-control form-control-sm" placeholder="{{ __('Set name') }}" required></div>
                        <div class="col-md-3"><input type="text" name="slug" class="form-control form-control-sm" placeholder="{{ __('slug') }}" required></div>
                        <div class="col-md-3"><input type="text" name="description" class="form-control form-control-sm" placeholder="{{ __('Description') }}"></div>
                        <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary">{{ __('Add Set') }}</button></div>
                    </form>

                    @push('scripts')
                    <script>
                    (function () {
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                        const section = document.getElementById('statusSetsSection');
                        if (!section || !csrf) return;

                        function saveSet(setCard) {
                            const setId = setCard.dataset.setId;
                            const statuses = Array.from(setCard.querySelectorAll('.js-set-status:checked')).map(c => c.value);
                            const defaultRadio = setCard.querySelector('.js-set-default:checked');
                            const payload = { statuses: statuses, default_status_id: defaultRadio ? defaultRadio.value : null };
                            fetch(setCard.dataset.url, {
                                method: 'PATCH',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                                body: JSON.stringify(payload),
                            })
                            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                            .then(() => { if (typeof showToast === 'function') showToast('{{ __('✓ Saved') }}', 'success'); })
                            .catch(() => { if (typeof showToast === 'function') showToast('{{ __('Save failed') }}', 'danger'); });
                        }

                        section.addEventListener('change', function (e) {
                            const el = e.target.closest('.js-set-status, .js-set-default');
                            if (!el) return;
                            const card = el.closest('[data-set-id]');
                            if (card) saveSet(card);
                        });
                    })();
                    </script>
                    @endpush
                </div>
            </div>
        </div>

        {{-- Club Identity --}}
        <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" aria-expanded="false" data-bs-target="#identitySection">{{ __('Club Identity') }}</button></h2>
            <div id="identitySection" class="accordion-collapse collapse" data-bs-parent="#clubAccordion">
                <div class="accordion-body">
                    <p class="text-muted small">{{ __('These details appear on public pages, emails, QR codes, and payment communications.') }}</p>
                    <form method="POST" action="{{ route('admin.settings.theme.update') }}">
                        @csrf
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Club Full Name') }}</label>
                                <input type="text" name="club_full_name" class="form-control" value="{{ $themeSettings['club_full_name'] ?? '' }}" placeholder="My Diving Club" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Short Code') }}</label>
                                <input type="text" name="club_short_code" class="form-control" value="{{ $themeSettings['club_short_code'] ?? '' }}" placeholder="MDC" maxlength="10">
                                <small class="text-muted">{{ __('Used in payment communications (e.g. MDC-2026-42-NAME)') }}</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Contact Email') }}</label>
                                <input type="email" name="club_email" class="form-control" value="{{ $themeSettings['club_email'] ?? '' }}" placeholder="info@club.example">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Postal Address') }}</label>
                                <input type="text" name="club_address" class="form-control" value="{{ $themeSettings['club_address'] ?? '' }}" placeholder="B.P. 1162, L-1011 Luxembourg">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Phone') }}</label>
                                <input type="text" name="club_phone" class="form-control" value="{{ $themeSettings['club_phone'] ?? '' }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Country') }}</label>
                                <input type="text" name="club_country" class="form-control" value="{{ $themeSettings['club_country'] ?? '' }}" placeholder="Luxembourg">
                            </div>
                        </div>
                        <hr>
                        <h6>@icon('🏠') {{ __('Warehouse / Club House') }}</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Warehouse Address') }}</label>
                                <input type="text" name="warehouse_address" class="form-control" value="{{ $themeSettings['warehouse_address'] ?? '' }}" placeholder="123 Main Street, City">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Latitude') }}</label>
                                <input type="text" name="warehouse_lat" class="form-control" value="{{ $themeSettings['warehouse_lat'] ?? '' }}" placeholder="49.6547">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Longitude') }}</label>
                                <input type="text" name="warehouse_lon" class="form-control" value="{{ $themeSettings['warehouse_lon'] ?? '' }}" placeholder="6.2197">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Save Club Identity') }}</button>
                    </form>

                    {{-- Training Locations --}}
                    <hr>
                    <h6>@icon('📍') {{ __('Training Locations') }}</h6>
                    <p class="text-muted small">{{ __('Add your pool, quarry, or meeting locations. These appear on the contact page with map links.') }}</p>
                    @php $locations = json_decode($themeSettings['training_locations'] ?? '[]', true) ?: []; @endphp
                    <div id="training-locations">
                        @foreach($locations as $i => $loc)
                            <div class="row g-2 mb-2 location-row">
                                <div class="col-md-4"><input type="text" name="loc_name[]" class="form-control form-control-sm" value="{{ $loc['name'] ?? '' }}" placeholder="{{ __('Name (e.g. City Pool)') }}"></div>
                                <div class="col-md-4"><input type="text" name="loc_address[]" class="form-control form-control-sm" value="{{ $loc['address'] ?? '' }}" placeholder="{{ __('Address') }}"></div>
                                <div class="col-md-1"><input type="text" name="loc_lat[]" class="form-control form-control-sm" value="{{ $loc['lat'] ?? '' }}" placeholder="{{ __('Lat') }}"></div>
                                <div class="col-md-1"><input type="text" name="loc_lon[]" class="form-control form-control-sm" value="{{ $loc['lon'] ?? '' }}" placeholder="{{ __('Lon') }}"></div>
                                <div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.location-row').remove()">✕</button></div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="document.getElementById('training-locations').insertAdjacentHTML('beforeend', '<div class=\'row g-2 mb-2 location-row\'><div class=\'col-md-4\'><input type=\'text\' name=\'loc_name[]\' class=\'form-control form-control-sm\' placeholder=\'{{ __("Name") }}\'></div><div class=\'col-md-4\'><input type=\'text\' name=\'loc_address[]\' class=\'form-control form-control-sm\' placeholder=\'{{ __("Address") }}\'></div><div class=\'col-md-1\'><input type=\'text\' name=\'loc_lat[]\' class=\'form-control form-control-sm\' placeholder=\'Lat\'></div><div class=\'col-md-1\'><input type=\'text\' name=\'loc_lon[]\' class=\'form-control form-control-sm\' placeholder=\'Lon\'></div><div class=\'col-md-2\'><button type=\'button\' class=\'btn btn-sm btn-outline-danger\' onclick=\'this.closest(&quot;.location-row&quot;).remove()\'>✕</button></div></div>')">+ {{ __('Add Location') }}</button>
                    <form method="POST" action="{{ route('admin.settings.theme.update') }}">
                        @csrf
                        <input type="hidden" name="training_locations" id="training_locations_json">
                        <button type="submit" class="btn btn-sm btn-primary" onclick="let locs=[];document.querySelectorAll('.location-row').forEach(r=>{let n=r.querySelector('[name=\'loc_name[]\']').value;if(n)locs.push({name:n,address:r.querySelector('[name=\'loc_address[]\']').value,lat:r.querySelector('[name=\'loc_lat[]\']').value,lon:r.querySelector('[name=\'loc_lon[]\']').value})});document.getElementById('training_locations_json').value=JSON.stringify(locs)">{{ __('Save Locations') }}</button>
                    </form>

                    {{-- Social Profile Links --}}
                    <hr>
                    <h6>@icon('🔗') {{ __('Social Media Profiles') }}</h6>
                    <p class="text-muted small">{{ __('Public profile URLs shown on the contact page. Leave blank to hide.') }}</p>
                    <form method="POST" action="{{ route('admin.settings.theme.update') }}">
                        @csrf
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><label class="form-label">Facebook</label><input type="url" name="social_facebook" class="form-control form-control-sm" value="{{ $themeSettings['social_facebook'] ?? '' }}" placeholder="https://facebook.com/yourclub"></div>
                            <div class="col-md-6"><label class="form-label">Instagram</label><input type="url" name="social_instagram" class="form-control form-control-sm" value="{{ $themeSettings['social_instagram'] ?? '' }}" placeholder="https://instagram.com/yourclub"></div>
                            <div class="col-md-6"><label class="form-label">YouTube</label><input type="url" name="social_youtube" class="form-control form-control-sm" value="{{ $themeSettings['social_youtube'] ?? '' }}" placeholder="https://youtube.com/@yourclub"></div>
                            <div class="col-md-6"><label class="form-label">TikTok</label><input type="url" name="social_tiktok" class="form-control form-control-sm" value="{{ $themeSettings['social_tiktok'] ?? '' }}" placeholder="https://tiktok.com/@yourclub"></div>
                            <div class="col-md-6"><label class="form-label">X / Twitter</label><input type="url" name="social_x" class="form-control form-control-sm" value="{{ $themeSettings['social_x'] ?? '' }}" placeholder="https://x.com/yourclub"></div>
                            <div class="col-md-6"><label class="form-label">WhatsApp</label><input type="url" name="social_whatsapp" class="form-control form-control-sm" value="{{ $themeSettings['social_whatsapp'] ?? '' }}" placeholder="https://chat.whatsapp.com/invite-link"></div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Save Social Links') }}</button>
                    </form>

                    <hr class="my-4">
                    <h6>📬 {{ __('Newsletter Settings') }}</h6>
                    <p class="text-muted small">{{ __('Override the base URL for article links in newsletters. Leave blank to use the current site URL.') }}</p>
                    <form method="POST" action="{{ route('admin.settings.theme.update') }}">
                        @csrf
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label">{{ __('Article Base URL') }}</label>
                                <input type="url" name="newsletter_article_base_url" class="form-control form-control-sm" value="{{ $themeSettings['newsletter_article_base_url'] ?? '' }}" placeholder="https://www.clubcep.eu">
                                <div class="form-text">{{ __('e.g. https://www.clubcep.eu — article links will become https://www.clubcep.eu/article/slug') }}</div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">{{ __('Newsletter Font') }}</label>
                                @php $currentFont = $themeSettings['newsletter_font'] ?? 'clean'; @endphp
                                <select name="newsletter_font" class="form-select form-select-sm">
                                    <option value="clean" @selected($currentFont === 'clean')>Clean — IBM Plex Sans</option>
                                    <option value="classic" @selected($currentFont === 'classic')>Classic — Libre Baskerville</option>
                                    <option value="sharp" @selected($currentFont === 'sharp')>Sharp — JetBrains Mono</option>
                                    <option value="modern" @selected($currentFont === 'modern')>Modern — DM Sans</option>
                                </select>
                                <div class="form-text">{{ __('Font used in email newsletters. Clean is the default.') }}</div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Save Newsletter Settings') }}</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Banking --}}
        <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" aria-expanded="false" data-bs-target="#bankingSection">{{ __('Banking (IBAN / SEPA)') }}</button></h2>
            <div id="bankingSection" class="accordion-collapse collapse" data-bs-parent="#clubAccordion">
                <div class="accordion-body">
                    <p class="text-muted small">{{ __('The club IBAN, BIC and beneficiary are shown on the membership dues calculator as the bank-transfer details.') }}</p>
                    <form method="POST" action="{{ route('admin.settings.theme.update') }}">
                        @csrf
                        <div class="row g-3 mb-3">
                            <div class="col-md-5">
                                <label class="form-label">{{ __('Club IBAN') }}</label>
                                <input type="text" name="club_iban" data-mask="iban" class="form-control" value="{{ $themeSettings['club_iban'] ?? '' }}" placeholder="LU00 0000 0000 0000 0000">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('BIC / SWIFT') }}</label>
                                <input type="text" name="club_bic" class="form-control" value="{{ $themeSettings['club_bic'] ?? '' }}" placeholder="BCEELULL">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Beneficiary Name') }}</label>
                                <input type="text" name="club_full_name" class="form-control" value="{{ $themeSettings['club_full_name'] ?? '' }}">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-5">
                                <label class="form-label">{{ __('Bank Name') }}</label>
                                <input type="text" name="club_bank_name" class="form-control" value="{{ $themeSettings['club_bank_name'] ?? '' }}" placeholder="BCEE">
                            </div>
                        </div>

                        <hr>
                        <p class="text-muted small mb-2">{{ __('Dues season taper: the reduction is evaluated at today plus the grace days below, so the cutoff falls a little later than today to leave time for processing. Set an absolute reference date to freeze the rate.') }}</p>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label" for="dues_cutoff_grace_days">{{ __('Cutoff grace (days)') }}</label>
                                <input type="number" min="0" max="120" id="dues_cutoff_grace_days" name="dues_cutoff_grace_days" class="form-control" value="{{ $themeSettings['dues_cutoff_grace_days'] ?? '0' }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="fee_taper_reference_date">{{ __('Freeze taper date (optional)') }}</label>
                                <input type="date" id="fee_taper_reference_date" name="fee_taper_reference_date" class="form-control" value="{{ $themeSettings['fee_taper_reference_date'] ?? '' }}">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Save Banking Details') }}</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Medical Compliance Rules --}}
        </div>{{-- end clubAccordion --}}
        </div>{{-- end tab-club --}}

        {{-- TAB 2: Rules & Compliance --}}
        <div class="tab-pane fade" id="tab-rules">
        <div class="accordion" id="rulesAccordion">

        {{-- Medical Compliance Rules --}}
        <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" aria-expanded="false" data-bs-target="#medRulesSection">{{ __('Medical Compliance Rules') }}</button></h2>
            <div id="medRulesSection" class="accordion-collapse collapse show" data-bs-parent="#rulesAccordion">
                <div class="accordion-body">
                    <p class="text-muted small">{{ __('Define which certificates are required per federation, age bracket, and type. Validity is in months from issue date.') }}</p>
                    <table class="table table-sm">
                        <thead><tr><th>{{ __('Federation') }}</th><th>{{ __('Age From') }}</th><th>{{ __('Age To') }}</th><th>{{ __('Cert Type') }}</th><th>{{ __('Validity (months)') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach($medicalRules as $r)
                            <tr>
                                <form method="POST" action="{{ route('admin.settings.medical-rule.update', $r) }}">
                                    @csrf @method('PUT')
                                    <td>
                                        <select name="federation_id" class="form-select form-select-sm">
                                            @foreach($federations as $f)
                                                <option value="{{ $f->id }}" {{ $r->federation_id == $f->id ? 'selected' : '' }}>{{ $f->acronym }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" name="age_bracket_low" class="form-control form-control-sm" value="{{ $r->age_bracket_low }}" min="0" style="width:70px"></td>
                                    <td><input type="number" name="age_bracket_high" class="form-control form-control-sm" value="{{ $r->age_bracket_high }}" min="0" style="width:70px"></td>
                                    <td>
                                        <select name="cert_type" class="form-select form-select-sm">
                                            @foreach(['gp' => 'GP', 'ent' => 'ENT', 'cardio' => 'Cardio', 'ophthalmologist' => 'Ophthalmologist', 'other' => 'Other'] as $v => $l)
                                                <option value="{{ $v }}" {{ $r->cert_type === $v ? 'selected' : '' }}>{{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" name="validity_months" class="form-control form-control-sm" value="{{ $r->validity_months }}" min="1" style="width:70px"></td>
                                    <td class="text-end text-nowrap">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Save') }}</button>
                                </form>
                                        <form method="POST" action="{{ route('admin.settings.medical-rule.destroy', $r) }}" class="d-inline" data-confirm="Delete?" data-confirm-style="danger" data-confirm-btn="{{ __('Confirm') }}">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">✕</button>
                                        </form>
                                    </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <form method="POST" action="{{ route('admin.settings.medical-rule.store') }}" class="row g-2 mt-2">
                        @csrf
                        <div class="col-md-2">
                            <select name="federation_id" class="form-select form-select-sm" required>
                                <option value="">{{ __('Federation') }}</option>
                                @foreach($federations as $f)<option value="{{ $f->id }}">{{ $f->acronym }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-1"><input type="number" name="age_bracket_low" class="form-control form-control-sm" placeholder="{{ __('From') }}" min="0" required></div>
                        <div class="col-md-1"><input type="number" name="age_bracket_high" class="form-control form-control-sm" placeholder="{{ __('To') }}" min="0" required></div>
                        <div class="col-md-2">
                            <select name="cert_type" class="form-select form-select-sm" required>
                                <option value="gp">GP</option><option value="ent">ENT</option><option value="cardio">Cardio</option><option value="ophthalmologist">Ophthalmologist</option><option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-2"><input type="number" name="validity_months" class="form-control form-control-sm" placeholder="{{ __('Months') }}" min="1" required></div>
                        <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary">{{ __('Add Rule') }}</button></div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Equipment Maintenance Rules --}}
        <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" aria-expanded="false" data-bs-target="#eqptRulesSection">{{ __('Equipment Maintenance Rules') }}</button></h2>
            <div id="eqptRulesSection" class="accordion-collapse collapse" data-bs-parent="#rulesAccordion">
                <div class="accordion-body">
                    <p class="text-muted small">{{ __('Define maintenance requirements per equipment type. Mandatory rules affect equipment compliance status.') }}</p>
                    <table class="table table-sm">
                        <thead><tr><th>{{ __('Equipment Type') }}</th><th>{{ __('Maintenance') }}</th><th>{{ __('Interval (months)') }}</th><th>{{ __('Mandatory') }}</th><th>{{ __('Regulation') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach($maintenanceRules as $r)
                            <tr>
                                <form method="POST" action="{{ route('admin.settings.maintenance-rule.update', $r) }}">
                                    @csrf @method('PUT')
                                    <td><input type="text" name="equipment_type" class="form-control form-control-sm" value="{{ $r->equipment_type }}" required></td>
                                    <td><input type="text" name="maintenance_name" class="form-control form-control-sm" value="{{ $r->maintenance_name }}" required></td>
                                    <td><input type="number" name="interval_months" class="form-control form-control-sm" value="{{ $r->interval_months }}" min="1" style="width:70px"></td>
                                    <td>
                                        <input type="hidden" name="is_mandatory" value="0">
                                        <input type="checkbox" name="is_mandatory" value="1" class="form-check-input" {{ $r->is_mandatory ? 'checked' : '' }}>
                                    </td>
                                    <td><input type="text" name="regulation_reference" class="form-control form-control-sm" value="{{ $r->regulation_reference }}"></td>
                                    <td class="text-end text-nowrap">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Save') }}</button>
                                </form>
                                        <form method="POST" action="{{ route('admin.settings.maintenance-rule.destroy', $r) }}" class="d-inline" data-confirm="Delete?" data-confirm-style="danger" data-confirm-btn="{{ __('Confirm') }}">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">✕</button>
                                        </form>
                                    </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <form method="POST" action="{{ route('admin.settings.maintenance-rule.store') }}" class="row g-2 mt-2">
                        @csrf
                        <div class="col-md-2"><input type="text" name="equipment_type" class="form-control form-control-sm" placeholder="{{ __('Type (e.g. regulator)') }}" required></div>
                        <div class="col-md-3"><input type="text" name="maintenance_name" class="form-control form-control-sm" placeholder="{{ __('Maintenance name') }}" required></div>
                        <div class="col-md-1"><input type="number" name="interval_months" class="form-control form-control-sm" placeholder="{{ __('Mo.') }}" min="1" required></div>
                        <div class="col-md-1">
                            <div class="form-check mt-1"><input type="hidden" name="is_mandatory" value="0"><input type="checkbox" name="is_mandatory" value="1" class="form-check-input" checked><label class="form-check-label small">{{ __('Mand.') }}</label></div>
                        </div>
                        <div class="col-md-3"><input type="text" name="regulation_reference" class="form-control form-control-sm" placeholder="{{ __('Regulation ref (optional)') }}"></div>
                        <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary">{{ __('Add Rule') }}</button></div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Theme & Appearance --}}
        </div>{{-- end rulesAccordion --}}
        </div>{{-- end tab-rules --}}

        {{-- TAB 3: Appearance --}}
        <div class="tab-pane fade" id="tab-appearance">
        <div class="accordion" id="appearanceAccordion">

        {{-- Theme & Appearance --}}
        <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" aria-expanded="false" data-bs-target="#themeSection">{{ __('Theme & Appearance') }}</button></h2>
            <div id="themeSection" class="accordion-collapse collapse show" data-bs-parent="#appearanceAccordion">
                <div class="accordion-body">
                    {{-- Presets --}}
                    {{-- Icon toggle (default for guests) --}}
                    <h6>{{ __('Menu Icons') }}</h6>
                    <form method="POST" action="{{ route('admin.settings.theme.update') }}" class="mb-4">
                        @csrf
                        <div class="form-check form-switch">
                            <input type="hidden" name="ui_show_icons" value="0">
                            <input class="form-check-input" type="checkbox" name="ui_show_icons" value="1" id="uiShowIcons" {{ ($themeSettings['ui_show_icons'] ?? '1') === '1' ? 'checked' : '' }} onchange="this.form.submit()">
                            <label class="form-check-label" for="uiShowIcons">{{ __('Show emoji icons in menus and headings (default for guests; members can override in their profile)') }}</label>
                        </div>
                    </form>

                    {{-- Site Layout --}}
                    <h6>{{ __('Site Layout') }}</h6>
                    <p class="text-muted small mb-2">{{ __('Controls the overall header, navigation, and page structure. Affects all visitors.') }}</p>
                    <div class="row g-3 mb-4" role="group" aria-label="{{ __('Site Layout') }}">
                        @foreach(\App\Services\ThemeService::layoutPresets() as $key => $meta)
                            @php $isActive = ($themeSettings['site_layout'] ?? 'default') === $key; @endphp
                            <div class="col-md-4">
                                <form method="POST" action="{{ route('admin.settings.theme.update') }}">
                                    @csrf
                                    <input type="hidden" name="site_layout" value="{{ $key }}">
                                    <button type="submit" class="w-100 text-start p-3 border rounded {{ $isActive ? 'border-primary bg-primary bg-opacity-10' : '' }}" aria-pressed="{{ $isActive ? 'true' : 'false' }}" title="{{ __($meta['desc']) }}">
                                        <div class="fw-bold mb-1">{{ $meta['icon'] }} {{ __($meta['label']) }}
                                            @if($isActive)
                                                <span class="badge bg-primary ms-1">{{ __('Active') }}</span>
                                            @endif
                                        </div>
                                        <div class="text-muted small">{{ __($meta['desc']) }}</div>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>

                    {{-- UI Style presets --}}
                    <h6>{{ __('UI Style') }}</h6>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach(\App\Services\ThemeService::stylePresets() as $key => $meta)
                            <form method="POST" action="{{ route('admin.settings.theme.update') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="ui_style" value="{{ $key }}">
                                <button class="btn btn-sm {{ ($themeSettings['ui_style'] ?? 'rounded') === $key ? 'btn-primary' : 'btn-outline-secondary' }}" title="{{ $meta['desc'] }}">
                                    {{ $meta['label'] }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                    <p class="text-muted small mb-3">
                        @php $currentStyle = \App\Services\ThemeService::stylePresets()[$themeSettings['ui_style'] ?? 'rounded'] ?? null; @endphp
                        {{ $currentStyle['desc'] ?? '' }}
                    </p>

                    {{-- Color Presets --}}
                    <h6>{{ __('Quick Presets') }}</h6>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach($themePresets as $name => $colors)
                            <form method="POST" action="{{ route('admin.settings.theme.preset') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="preset" value="{{ $name }}">
                                <button class="btn btn-sm {{ ($themeSettings['preset'] ?? '') === $name ? 'btn-primary' : 'btn-outline-secondary' }}" style="border-left: 4px solid {{ $colors['primary_color'] }}">
                                    {{ ucfirst($name) }}
                                </button>
                            </form>
                        @endforeach
                    </div>

                    {{-- Custom colors --}}
                    <form method="POST" action="{{ route('admin.settings.theme.update') }}">
                        @csrf
                        <h6>{{ __('Colors') }}</h6>
                        <div class="row g-2 mb-3">
                            @foreach(['primary_color' => 'Primary', 'secondary_color' => 'Secondary', 'accent_color' => 'Accent', 'header_gradient_start' => 'Header Start', 'header_gradient_end' => 'Header End', 'footer_bg' => 'Footer BG'] as $key => $label)
                                <div class="col-md-2">
                                    <label class="form-label small">{{ $label }}</label>
                                    <input type="color" name="{{ $key }}" class="form-control form-control-color w-100" value="{{ $themeSettings[$key] ?? '#003366' }}">
                                </div>
                            @endforeach
                        </div>

                        <h6>{{ __('Branding') }}</h6>
                        <div class="row g-2 mb-3">
                            <div class="col-md-1"><label class="form-label small">{{ __('Emoji') }}</label><input type="text" name="logo_emoji" class="form-control form-control-sm" value="{{ $themeSettings['logo_emoji'] ?? '🤿' }}"></div>
                            <div class="col-md-2"><label class="form-label small">{{ __('Accent Text') }}</label><input type="text" name="logo_accent_text" class="form-control form-control-sm" value="{{ $themeSettings['logo_accent_text'] ?? 'Diving' }}"></div>
                            <div class="col-md-2"><label class="form-label small">{{ __('Plain Text') }}</label><input type="text" name="logo_plain_text" class="form-control form-control-sm" value="{{ $themeSettings['logo_plain_text'] ?? 'Club' }}"></div>
                            <div class="col-md-4"><label class="form-label small">{{ __('Club Full Name') }}</label><input type="text" name="club_full_name" class="form-control form-control-sm" value="{{ $themeSettings['club_full_name'] ?? '' }}"></div>
                        </div>

                        <h6>{{ __('Layout') }}</h6>
                        <div class="row g-2 mb-3">
                            <div class="col-md-3">
                                <label class="form-label small">{{ __('Width') }}</label>
                                <select name="layout_width" class="form-select form-select-sm">
                                    @foreach(['container' => 'Normal', 'container-lg' => 'Wide', 'container-xl' => 'Extra Wide', 'container-fluid' => 'Full Width'] as $v => $l)
                                        <option value="{{ $v }}" {{ ($themeSettings['layout_width'] ?? 'container-lg') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">{{ __('Header Bubbles') }}</label>
                                <select name="header_bubbles" class="form-select form-select-sm">
                                    <option value="1" {{ ($themeSettings['header_bubbles'] ?? '1') === '1' ? 'selected' : '' }}>{{ __('On') }}</option>
                                    <option value="0" {{ ($themeSettings['header_bubbles'] ?? '1') === '0' ? 'selected' : '' }}>{{ __('Off') }}</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Save Theme') }}</button>
                    </form>

                    {{-- Article type backgrounds --}}
                    <hr>
                    <h6>{{ __('Article Type Backgrounds') }}</h6>
                    <form method="POST" action="{{ route('admin.settings.theme.update') }}">
                        @csrf
                        <div class="row g-2 mb-3">
                            @foreach(\App\Models\Article::TYPES as $key => $meta)
                                <div class="col-md-3 col-lg-2">
                                    <label class="form-label small">{{ $meta['icon'] }} {{ __($meta['label']) }}</label>
                                    <input type="color" name="article_bg_{{ $key }}" class="form-control form-control-color w-100" value="{{ $themeSettings['article_bg_' . $key] ?? $meta['color'] . '10' }}">
                                </div>
                            @endforeach
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Save') }}</button>
                    </form>

                    {{-- Logo upload --}}
                    <hr>
                    <h6>{{ __('Custom Logo') }}</h6>
                    <form method="POST" action="{{ route('admin.settings.theme.logo') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-4"><input type="file" name="logo" class="form-control form-control-sm" accept="image/*"></div>
                        <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary">{{ __('Upload') }}</button></div>
                        @if($themeSettings['logo_image'] ?? null)
                            <div class="col-md-3"><img src="{{ asset('storage/' . $themeSettings['logo_image']) }}" alt="Logo" style="max-height:40px"></div>
                        @endif
                    </form>
                </div>
            </div>
        </div>

        {{-- API Configuration Guide --}}
        </div>{{-- end appearanceAccordion --}}
        </div>{{-- end tab-appearance --}}

        {{-- TAB 4: Technical --}}
        <div class="tab-pane fade" id="tab-technical">
        <div class="accordion" id="technicalAccordion">

        {{-- API Configuration Guide --}}
        <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" aria-expanded="false" data-bs-target="#apiSection">{{ __('API Keys & Configuration') }}</button></h2>
            <div id="apiSection" class="accordion-collapse collapse show" data-bs-parent="#technicalAccordion">
                <div class="accordion-body">
                    <p class="text-muted">{{ __('API keys are stored in the .env file on the server. Below is the current status of each integration.') }}</p>
                    <table class="table table-sm">
                        <thead><tr><th>{{ __('Service') }}</th><th>{{ __('Status') }}</th><th>{{ __('Notes') }}</th></tr></thead>
                        <tbody>
                            @php $keys = [
                                'Google OAuth' => ['key' => 'services.google.client_id', 'help' => 'console.cloud.google.com/apis/credentials'],
                                'Microsoft OAuth' => ['key' => 'services.microsoft.client_id', 'help' => 'portal.azure.com'],
                                'Facebook OAuth' => ['key' => 'services.facebook.client_id', 'help' => 'developers.facebook.com/apps'],
                                'X OAuth' => ['key' => 'services.x.client_id', 'help' => 'developer.x.com'],
                                'Amazon OAuth' => ['key' => 'services.amazon.client_id', 'help' => 'developer.amazon.com/loginwithamazon'],
                                'Google Maps' => ['key' => 'club.google_maps_key', 'help' => 'console.cloud.google.com — Maps JavaScript API'],
                            ]; @endphp
                            @foreach($keys as $name => $info)
                                <tr>
                                    <td>{{ $name }}</td>
                                    <td>
                                        @if(config($info['key']))
                                            <span class="badge bg-success">{{ __('Configured') }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark">{{ __('Not set') }}</span>
                                        @endif
                                    </td>
                                    <td class="small text-muted">{{ $info['help'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="alert alert-info small mt-3">
                        {{ __('To update API keys, edit the .env file on the server and run') }} <code>php artisan config:clear</code>
                    </div>
                </div>
            </div>
        </div>

        {{-- Social Media Auto-Publish --}}
        <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" aria-expanded="false" data-bs-target="#socialSection">{{ __('Social Media Auto-Publish') }}</button></h2>
            <div id="socialSection" class="accordion-collapse collapse" data-bs-parent="#technicalAccordion">
                <div class="accordion-body">
                    <p class="text-muted small">{{ __('When enabled, event photos with GDPR consent will be auto-published to configured social media platforms.') }}</p>
                    <form method="POST" action="{{ route('admin.settings.theme.update') }}">
                        @csrf
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Auto-Publish') }}</label>
                                <select name="social_auto_publish" class="form-select">
                                    <option value="0" {{ ($themeSettings['social_auto_publish'] ?? '0') === '0' ? 'selected' : '' }}>{{ __('Disabled') }}</option>
                                    <option value="1" {{ ($themeSettings['social_auto_publish'] ?? '0') === '1' ? 'selected' : '' }}>{{ __('Enabled') }}</option>
                                </select>
                            </div>
                        </div>

                        <h6 class="mt-3">Facebook <small class="text-muted">({{ __('private group') }})</small></h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Publish to Facebook') }}</label>
                                <select name="fb_publish_enabled" class="form-select">
                                    <option value="0" {{ ($themeSettings['fb_publish_enabled'] ?? '1') === '0' ? 'selected' : '' }}>{{ __('No') }}</option>
                                    <option value="1" {{ ($themeSettings['fb_publish_enabled'] ?? '1') === '1' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('FB Group is Closed') }}</label>
                                <select name="fb_group_is_closed" class="form-select">
                                    <option value="0" {{ ($themeSettings['fb_group_is_closed'] ?? '0') === '0' ? 'selected' : '' }}>{{ __('No / Unknown') }}</option>
                                    <option value="1" {{ ($themeSettings['fb_group_is_closed'] ?? '0') === '1' ? 'selected' : '' }}>{{ __('Yes, confirmed closed') }}</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Facebook Group ID') }}</label>
                                <input type="text" name="fb_group_id" class="form-control" value="{{ $themeSettings['fb_group_id'] ?? '' }}" placeholder="123456789012345">
                            </div>
                        </div>

                        <h6 class="mt-3">Instagram <small class="text-muted">({{ __('public — content rules may differ') }})</small></h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Publish to Instagram') }}</label>
                                <select name="ig_publish_enabled" class="form-select">
                                    <option value="0" {{ ($themeSettings['ig_publish_enabled'] ?? '0') === '0' ? 'selected' : '' }}>{{ __('No') }}</option>
                                    <option value="1" {{ ($themeSettings['ig_publish_enabled'] ?? '0') === '1' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Instagram Business Account ID') }}</label>
                                <input type="text" name="ig_account_id" class="form-control" value="{{ $themeSettings['ig_account_id'] ?? '' }}" placeholder="17841400000000000">
                            </div>
                        </div>

                        <div class="alert alert-info small py-2">
                            ℹ️ {{ __('Facebook: requires') }} <code>FACEBOOK_PAGE_TOKEN</code> {{ __('in .env. Group must be confirmed closed.') }}<br>
                            ℹ️ {{ __('Instagram: requires') }} <code>INSTAGRAM_ACCESS_TOKEN</code> {{ __('in .env. Uses Instagram Graph API (Business account). Since Instagram is public, photos with faces or from minors are automatically excluded.') }}
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Save Social Settings') }}</button>
                    </form>
                </div>
            </div>
        </div>

    </div>{{-- end technicalAccordion --}}
    </div>{{-- end tab-technical --}}

    {{-- TAB: Languages --}}
    <div class="tab-pane fade" id="tab-languages">
        <div class="card dc-card">
            <div class="card-body">
                <h6>@icon('🌍') {{ __('Enabled Languages') }}</h6>
                <p class="text-muted small">{{ __('Uncheck languages you don\'t need. The language selector and translations will only show enabled languages.') }}</p>
                <form method="POST" action="{{ route('admin.settings.theme.update') }}">
                    @csrf
                    @php $enabled = \App\Http\Middleware\SetLocale::enabledLocales(); @endphp
                    <div class="mb-3">
                        <label class="form-label fw-bold">{{ __('Default Language') }}</label>
                        <select name="default_locale" class="form-select form-select-sm" style="max-width:300px">
                            @foreach(config('languages', []) as $code => $lang)
                                @if(in_array($code, $enabled))
                                    <option value="{{ $code }}" {{ \App\Models\ThemeSetting::get('default_locale', config('app.locale')) === $code ? 'selected' : '' }}>
                                        {{ $lang['flag'] }} {{ $lang['native'] }} ({{ $lang['label'] }})
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        <small class="text-muted">{{ __('Used for new visitors and as the fallback language.') }}</small>
                    </div>
                    <div class="row">
                        @foreach(config('languages', []) as $code => $lang)
                            <div class="col-6 col-md-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="enabled_locales[]" value="{{ $code }}" id="locale_{{ $code }}"
                                        {{ in_array($code, $enabled) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="locale_{{ $code }}">
                                        {{ $lang['flag'] }} {{ $lang['native'] }} <span class="text-muted small">({{ $lang['label'] }})</span>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm mt-3">{{ __('Save Languages') }}</button>
                </form>
            </div>
        </div>
    </div>

    {{-- TAB 5: License --}}
    <div class="tab-pane fade" id="tab-license">
        @php $lic = \App\Services\LicenseService::status(); @endphp
        <div class="card dc-card mb-3">
            <div class="card-body">
                <h6>@icon('📊') {{ __('Installation Status') }}</h6>
                <p>{{ __('Active members') }}: <strong>{{ $lic['member_count'] }}</strong> / {{ $lic['free_tier_limit'] }} {{ __('free tier') }}</p>
                @if($lic['is_valid'])
                    <span class="badge bg-success fs-6">@icon('✅') {{ $lic['needs_license'] ? __('Licensed') : __('Free Tier') }}</span>
                @else
                    <span class="badge bg-danger fs-6">@icon('🔒') {{ __('License Required') }}</span>
                    <p class="text-danger mt-2">{{ __('New member registration is blocked. Enter a valid license key below.') }}</p>
                @endif
                @if(!($lic['integrity_ok'] ?? true))
                    <div class="alert alert-danger mt-2 mb-0 py-1 small">@icon('⚠️') {{ __('Integrity check failed — license service may have been tampered with.') }}</div>
                @endif
            </div>
        </div>
        <div class="card dc-card">
            <div class="card-body">
                <h6>@icon('🔑') {{ __('License Key') }}</h6>
                <form method="POST" action="{{ route('admin.settings.theme.update') }}">
                    @csrf
                    <div class="mb-3">
                        <textarea name="license_key" class="form-control font-monospace" rows="3" placeholder="{{ __('Paste your license key here...') }}">{{ $themeSettings['license_key'] ?? '' }}</textarea>
                        <small class="text-muted">{{ __('License keys are signed codes that unlock member registration beyond 100 members.') }}</small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Save License Key') }}</button>
                </form>
            </div>
        </div>
    </div>{{-- end tab-license --}}

    </div>{{-- end tab-content --}}

    <script>
    // Remember open tab + accordion section across saves
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(t => t.addEventListener('shown.bs.tab', () => sessionStorage.setItem('settings_tab', t.dataset.bsTarget)));
    document.querySelectorAll('.accordion-collapse').forEach(c => c.addEventListener('shown.bs.collapse', () => sessionStorage.setItem('settings_section', c.id)));
    const savedTab = sessionStorage.getItem('settings_tab');
    const savedSection = sessionStorage.getItem('settings_section');
    if (savedTab) { const t = document.querySelector('[data-bs-target="'+savedTab+'"]'); if (t) new bootstrap.Tab(t).show(); }
    if (savedSection) { const s = document.getElementById(savedSection); if (s) new bootstrap.Collapse(s, {toggle: true}); }
    </script>
</x-admin-layout>
