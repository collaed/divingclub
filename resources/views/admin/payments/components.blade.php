<x-admin-layout :title="__('Fee Components')">
    <h4 class="mb-4">{{ __('Fee Components') }}</h4>

    <p class="text-muted small">
        {{ __('Age taper: when a member is younger than the age at the anchor date, the component amount is multiplied by the ratio (0 = free, 0.5 = half). Leave age or ratio empty to disable. Anchor defaults to the season start.') }}
    </p>

    <div class="table-responsive mb-4">
        <table class="table table-sm align-middle" id="componentsTable">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Slug') }}</th>
                    <th>{{ __('Amount') }}</th>
                    <th>{{ __('Base') }}</th>
                    <th>{{ __('Optional') }}</th>
                    <th title="{{ __('Reduced by season fee taper') }}">{{ __('Taperable') }}</th>
                    <th title="{{ __('Free/discounted below this age') }}">{{ __('Age <') }}</th>
                    <th title="{{ __('Multiplier applied below the age (0 = free)') }}">{{ __('Ratio') }}</th>
                    <th title="{{ __('Age measured at this date (defaults to season start)') }}">{{ __('Anchor') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($components as $c)
                <tr data-id="{{ $c->id }}" data-url="{{ route('admin.payments.component.update', $c) }}">
                    <td>{{ $c->name }}</td>
                    <td><code>{{ $c->slug }}</code></td>
                    <td>€{{ number_format($c->amount, 2) }}</td>
                    <td>@if($c->is_base) <span>✓</span> @endif</td>
                    <td>@if($c->is_optional) <span>✓</span> @endif</td>
                    <td>@if($c->prorata_eligible) <span>✓</span> @endif</td>
                    <td style="max-width:5rem">
                        <input type="number" min="0" max="120" step="1" name="taper_below_age"
                               class="form-control form-control-sm js-component-field"
                               value="{{ $c->taper_below_age }}" placeholder="—">
                    </td>
                    <td style="max-width:5.5rem">
                        <input type="number" min="0" max="1" step="0.01" name="taper_ratio"
                               class="form-control form-control-sm js-component-field"
                               value="{{ $c->taper_ratio !== null ? rtrim(rtrim(number_format((float) $c->taper_ratio, 3), '0'), '.') : '' }}" placeholder="—">
                    </td>
                    <td style="max-width:9rem">
                        <input type="date" name="age_anchor_date"
                               class="form-control form-control-sm js-component-field"
                               value="{{ $c->age_anchor_date?->format('Y-m-d') }}">
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.payments.component.destroy', $c) }}" class="d-inline" data-confirm="{{ __('Delete?') }}" data-confirm-style="danger">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">✕</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="card dc-card">
        <div class="card-header">{{ __('Add Component') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.payments.component.store') }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-3"><input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror" placeholder="{{ __('Name') }}" required>@error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                    <div class="col-md-2"><input type="text" name="slug" class="form-control form-control-sm @error('slug') is-invalid @enderror" placeholder="{{ __('Slug') }}" required>@error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                    <div class="col-md-2"><input type="number" name="amount" class="form-control form-control-sm @error('amount') is-invalid @enderror" placeholder="{{ __('Amount') }}" step="0.01" required>@error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                    <div class="col-md-4">
                        <div class="form-check form-check-inline"><input type="checkbox" name="is_base" value="1" class="form-check-input"><label class="form-check-label small">{{ __('Base') }}</label></div>
                        <div class="form-check form-check-inline"><input type="checkbox" name="is_optional" value="1" class="form-check-input"><label class="form-check-label small">{{ __('Optional') }}</label></div>
                        <div class="form-check form-check-inline"><input type="checkbox" name="prorata_eligible" value="1" class="form-check-input"><label class="form-check-label small">{{ __('Taperable') }}</label></div>
                    </div>
                    <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">{{ __('Add') }}</button></div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-2"><input type="number" name="taper_below_age" class="form-control form-control-sm" placeholder="{{ __('Age <') }}" min="0" max="120" step="1"></div>
                    <div class="col-md-2"><input type="number" name="taper_ratio" class="form-control form-control-sm" placeholder="{{ __('Ratio (0-1)') }}" min="0" max="1" step="0.01"></div>
                    <div class="col-md-3"><input type="date" name="age_anchor_date" class="form-control form-control-sm" title="{{ __('Age anchor date') }}"></div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const table = document.getElementById('componentsTable');
        if (!table || !csrf) return;

        let timer = null;

        function save(input) {
            const row = input.closest('tr[data-url]');
            if (!row) return;
            const payload = {};
            payload[input.name] = input.value;

            fetch(row.dataset.url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            })
            .then(r => { if (!r.ok) return r.text().then(t => { throw new Error('HTTP ' + r.status); }); return r.json(); })
            .then(() => { if (typeof showToast === 'function') showToast('{{ __('✓ Saved') }}', 'success'); })
            .catch(() => { if (typeof showToast === 'function') showToast('{{ __('Save failed') }}', 'danger'); });
        }

        table.addEventListener('input', function (e) {
            const input = e.target.closest('.js-component-field');
            if (!input) return;
            clearTimeout(timer);
            timer = setTimeout(() => save(input), 300);
        });
        table.addEventListener('change', function (e) {
            const input = e.target.closest('.js-component-field');
            if (!input || input.type !== 'date') return;
            clearTimeout(timer);
            save(input);
        });
    })();
    </script>
    @endpush
</x-admin-layout>
