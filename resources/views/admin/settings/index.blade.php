<x-layout :title="__('System Settings')">
    <h4 class="mb-4">{{ __('System Settings') }}</h4>

    {{-- Tab navigation --}}
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-club" type="button">🏢 {{ __('Club & Finance') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-rules" type="button">📋 {{ __('Rules & Compliance') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-appearance" type="button">🎨 {{ __('Appearance') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-technical" type="button">⚙️ {{ __('Technical') }}</button></li>
    </ul>

    <div class="tab-content">

    {{-- TAB 1: Club & Finance --}}
    <div class="tab-pane fade show active" id="tab-club">
    <div class="accordion" id="clubAccordion">

        {{-- Federations --}}
        <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#fedSection">{{ __('Federations') }}</button></h2>
            <div id="fedSection" class="accordion-collapse collapse show" data-bs-parent="#clubAccordion">
                <div class="accordion-body">
                    <table class="table table-sm">
                        <thead><tr><th>{{ __('Acronym') }}</th><th>{{ __('Full Name') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach($federations as $fed)
                            <tr>
                                <form method="POST" action="{{ route('admin.settings.federation.update', $fed) }}">
                                    @csrf @method('PUT')
                                    <td><input type="text" name="acronym" class="form-control form-control-sm" value="{{ $fed->acronym }}" required></td>
                                    <td><input type="text" name="full_name" class="form-control form-control-sm" value="{{ $fed->full_name }}" required></td>
                                    <td class="text-end text-nowrap">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Save') }}</button>
                                </form>
                                        <form method="POST" action="{{ route('admin.settings.federation.destroy', $fed) }}" class="d-inline" onsubmit="return confirm('Delete?')">
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
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#statusSection">{{ __('Member Statuses') }}</button></h2>
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
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#feesSection">{{ __('Membership Fees (per Year)') }}</button></h2>
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
                                <td><form method="POST" action="{{ route('admin.settings.membership-fee.destroy', $mf) }}" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">✕</button></form></td>
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

        {{-- Banking --}}
        <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#bankingSection">{{ __('Banking (IBAN / SEPA)') }}</button></h2>
            <div id="bankingSection" class="accordion-collapse collapse" data-bs-parent="#clubAccordion">
                <div class="accordion-body">
                    <p class="text-muted small">{{ __('The club IBAN is used to generate EPC QR codes on the dues calculator and payment pages.') }}</p>
                    <form method="POST" action="{{ route('admin.settings.theme.update') }}">
                        @csrf
                        <div class="row g-3 mb-3">
                            <div class="col-md-5">
                                <label class="form-label">{{ __('Club IBAN') }}</label>
                                <input type="text" name="club_iban" class="form-control" value="{{ $themeSettings['club_iban'] ?? '' }}" placeholder="LU00 0000 0000 0000 0000">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('BIC / SWIFT') }}</label>
                                <input type="text" name="club_bic" class="form-control" value="{{ $themeSettings['club_bic'] ?? '' }}" placeholder="BCEELULL">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Beneficiary Name') }}</label>
                                <input type="text" name="club_full_name" class="form-control" value="{{ $themeSettings['club_full_name'] ?? '' }}" placeholder="Club Européen de Plongée">
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
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#medRulesSection">{{ __('Medical Compliance Rules') }}</button></h2>
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
                                        <form method="POST" action="{{ route('admin.settings.medical-rule.destroy', $r) }}" class="d-inline" onsubmit="return confirm('Delete?')">
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
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#eqptRulesSection">{{ __('Equipment Maintenance Rules') }}</button></h2>
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
                                        <form method="POST" action="{{ route('admin.settings.maintenance-rule.destroy', $r) }}" class="d-inline" onsubmit="return confirm('Delete?')">
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
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#themeSection">{{ __('Theme & Appearance') }}</button></h2>
            <div id="themeSection" class="accordion-collapse collapse show" data-bs-parent="#appearanceAccordion">
                <div class="accordion-body">
                    {{-- Presets --}}
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
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#apiSection">{{ __('API Keys & Configuration') }}</button></h2>
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

    </div>{{-- end technicalAccordion --}}
    </div>{{-- end tab-technical --}}

    </div>{{-- end tab-content --}}
</x-layout>
