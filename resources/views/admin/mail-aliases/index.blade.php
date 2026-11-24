<x-admin-layout :title="__('Mail Aliases')">
    @php($isBureauMaster = auth()->user()->hasRole('bureau_master'))

    <h4 class="mb-1">{{ __('Mail Aliases') }}</h4>
    <p class="text-muted small mb-4">{{ __('Define a mailing alias by ticking the member properties it must match (all selected properties must hold at once), and/or by naming specific people to always include or exclude. Sending to any of the alias\'s tags — e.g. :example — reaches everyone who currently matches. Give an alias several tags (e.g. "bureau, board") to create synonyms that can never drift apart, since they share one definition.', ['example' => \App\Services\MailAliasService::mailtoAddress('{tag}')]) }}</p>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card dc-card">
                <div class="card-header">{{ __('Existing aliases') }}</div>
                <div class="card-body p-0">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __('Tags') }}</th><th>{{ __('Label') }}</th><th class="text-end">{{ __('Members') }}</th><th></th>@if($isBureauMaster)<th class="text-end">{{ __('Actions') }}</th>@endif</tr></thead>
                        <tbody>
                        @forelse($definitions as $row)
                            @php($d = $row['definition'])
                            <tr data-definition data-id="{{ $d->id }}" data-tags="{{ implode(', ', $d->tags) }}" data-label="{{ $d->label }}" data-auth-level="{{ $d->auth_level }}" data-criteria="{{ json_encode($d->criteria) }}" data-include-ids="{{ json_encode($d->include_user_ids) }}" data-exclude-ids="{{ json_encode($d->exclude_user_ids) }}" data-contact-ids="{{ json_encode($d->include_contact_ids) }}" data-include-all-contacts="{{ $d->include_all_contacts ? '1' : '0' }}" data-update-url="{{ route('admin.mail-aliases.update', $d) }}">
                                <td>@foreach($d->tags as $tag)<code>{{ $tag }}</code>@if(! $loop->last), @endif @endforeach</td>
                                <td>{{ $d->label }}</td>
                                <td class="text-end">{{ $row['count'] }}</td>
                                <td>@unless($d->enabled)<span class="badge bg-secondary">{{ __('Disabled') }}</span>@endunless</td>
                                @if($isBureauMaster)
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-edit-definition>{{ __('Edit') }}</button>
                                    <form method="POST" action="{{ route('admin.mail-aliases.destroy', $d) }}" class="d-inline" data-confirm-form data-confirm="{{ __('Delete the “:label” alias?', ['label' => $d->label]) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">@icon('🗑️')</button>
                                    </form>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted text-center py-3">{{ __('No dynamic aliases yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card dc-card">
                <div class="card-header">{{ __('Build an alias') }}</div>
                <div class="card-body">
                    @if($isBureauMaster)
                    <form id="aliasBuilderForm" method="POST" action="{{ route('admin.mail-aliases.store') }}">
                        @csrf
                        <input type="hidden" name="_method" id="builderMethod" value="POST">
                        <input type="hidden" id="builderActionStore" value="{{ route('admin.mail-aliases.store') }}">
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Tags') }}</label>
                                <input type="text" name="tags" id="builderTags" class="form-control form-control-sm @error('tags') is-invalid @enderror" required placeholder="n1-renewal-chase, n1-chase">
                                <div class="form-text">{{ __('Comma-separated. Add a synonym here instead of a second alias, so it can\'t drift from this one.') }}</div>
                                @error('tags') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">{{ __('Label') }}</label>
                                <input type="text" name="label" id="builderLabel" class="form-control form-control-sm @error('label') is-invalid @enderror" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Who can send') }}</label>
                                <select name="auth_level" id="builderAuthLevel" class="form-select form-select-sm">
                                    @foreach(\App\Models\MailAliasDefinition::AUTH_LEVELS as $value => $authLabel)
                                        <option value="{{ $value }}">{{ __($authLabel) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div id="builderCriteria" class="row g-2 mb-3">
                            @foreach($options as $key => $label)
                                <div class="col-md-6 form-check">
                                    <input type="checkbox" class="form-check-input" name="criteria[]" value="{{ $key }}" id="crit-{{ $key }}" data-criterion>
                                    <label class="form-check-label small" for="crit-{{ $key }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text mb-2">{{ __('Leave every property unticked to build the alias from named members or mailing-list contacts only.') }}</div>

                        @include('admin.mail-aliases._member-pickers')
                        @include('admin.mail-aliases._contact-picker')

                        <div class="d-flex gap-2 mb-3">
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Save alias') }}</button>
                            <button type="button" id="builderCancelEdit" class="btn btn-sm btn-outline-secondary d-none">{{ __('Cancel edit') }}</button>
                        </div>
                    </form>
                    @else
                        <p class="text-muted small">{{ __('Only bureau_master can create or edit aliases. You can still preview who any selection would match below.') }}</p>
                        <div id="builderCriteria" class="row g-2 mb-3">
                            @foreach($options as $key => $label)
                                <div class="col-md-6 form-check">
                                    <input type="checkbox" class="form-check-input" id="crit-{{ $key }}" data-criterion value="{{ $key }}">
                                    <label class="form-check-label small" for="crit-{{ $key }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>

                        @include('admin.mail-aliases._member-pickers')
                        @include('admin.mail-aliases._contact-picker')
                    @endif

                    <hr>
                    <h6 class="d-flex justify-content-between align-items-center">
                        <span>{{ __('Matching members') }} (<span id="previewCount">0</span>)</span>
                    </h6>
                    <div id="previewList" class="small" style="max-height: 20rem; overflow-y: auto;">
                        <p class="text-muted">{{ __('Tick a property, or add a named member, to see who it matches.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const previewUrl = @json(route('admin.mail-aliases.preview'));
        const criteriaBox = document.getElementById('builderCriteria');
        const countEl = document.getElementById('previewCount');
        const listEl = document.getElementById('previewList');
        const users = @json($availableUsers->map(fn ($u) => ['id' => $u->id, 'name' => trim(($u->detail?->first_name ?? '').' '.($u->detail?->last_name ?? '')) ?: $u->name, 'email' => $u->primary_email])->values());
        const contacts = @json($availableContacts->map(fn ($c) => ['id' => $c->id, 'name' => $c->name ?: $c->email, 'email' => $c->email])->values());
        let timer = null;

        // ─── Named chip pickers (members: include/exclude; external contacts: a single include list) ───
        const kindConfig = {
            include: { source: users, field: 'include_user_ids' },
            exclude: { source: users, field: 'exclude_user_ids' },
            contact: { source: contacts, field: 'include_contact_ids' },
        };

        function chipList(kind) { return document.querySelector('[data-chip-list="' + kind + '"]'); }

        function chipIds(kind) {
            return Array.from(chipList(kind).querySelectorAll('input[type=hidden]')).map(function (el) { return parseInt(el.value, 10); });
        }

        function addChip(kind, id) {
            const list = chipList(kind);
            if (!list || chipIds(kind).includes(id)) { return; }
            const entry = kindConfig[kind].source.find(function (u) { return u.id === id; });
            if (!entry) { return; }
            const chip = document.createElement('span');
            chip.className = 'badge bg-light text-dark border d-inline-flex align-items-center gap-1';
            chip.innerHTML = '<input type="hidden" name="' + kindConfig[kind].field + '[]" value="' + id + '">' + entry.name
                + ' <button type="button" class="btn-close btn-close-sm" style="font-size:.55rem" aria-label="{{ __('Remove') }}"></button>';
            chip.querySelector('button').addEventListener('click', function () {
                chip.remove();
                clearTimeout(timer);
                timer = setTimeout(runPreview, 300);
            });
            list.appendChild(chip);
        }

        function setChips(kind, ids) {
            const list = chipList(kind);
            if (list) { list.innerHTML = ''; }
            (ids || []).forEach(function (id) { addChip(kind, id); });
        }

        Object.keys(kindConfig).forEach(function (kind) {
            const input = document.querySelector('[data-member-combo="' + kind + '"]');
            const dropdown = document.querySelector('[data-member-dropdown="' + kind + '"]');
            if (!input || !dropdown) { return; }
            const source = kindConfig[kind].source;

            function render(filter) {
                const f = filter.toLowerCase();
                const matches = (f ? source.filter(function (u) { return u.name.toLowerCase().includes(f) || u.email.toLowerCase().includes(f); }) : source).slice(0, 15);
                dropdown.innerHTML = matches.map(function (u) {
                    return '<button type="button" class="dropdown-item small" data-id="' + u.id + '">' + u.name + ' <span class="text-muted">' + u.email + '</span></button>';
                }).join('');
                dropdown.classList.toggle('show', matches.length > 0);
            }

            input.addEventListener('focus', function () { render(input.value); });
            input.addEventListener('input', function () { render(input.value); });
            dropdown.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-id]');
                if (!btn) { return; }
                addChip(kind, parseInt(btn.dataset.id, 10));
                input.value = '';
                dropdown.classList.remove('show');
                clearTimeout(timer);
                timer = setTimeout(runPreview, 300);
            });
            document.addEventListener('click', function (e) {
                if (!e.target.closest('[data-member-combo="' + kind + '"], [data-member-dropdown="' + kind + '"]')) { dropdown.classList.remove('show'); }
            });
        });

        function selectedCriteria() {
            return Array.from(criteriaBox.querySelectorAll('[data-criterion]:checked')).map(function (el) { return el.value; });
        }

        const includeAllContactsInput = document.getElementById('builderIncludeAllContacts');

        function runPreview() {
            const criteria = selectedCriteria();
            const includeIds = chipIds('include');
            const contactIds = chipIds('contact');
            const includeAllContacts = includeAllContactsInput ? includeAllContactsInput.checked : false;
            if (criteria.length === 0 && includeIds.length === 0 && contactIds.length === 0 && !includeAllContacts) {
                countEl.textContent = '0';
                listEl.innerHTML = '<p class="text-muted">{{ __('Tick a property, or add a named member, to see who it matches.') }}</p>';
                return;
            }
            const body = new URLSearchParams();
            criteria.forEach(function (c) { body.append('criteria[]', c); });
            includeIds.forEach(function (id) { body.append('include_user_ids[]', id); });
            chipIds('exclude').forEach(function (id) { body.append('exclude_user_ids[]', id); });
            contactIds.forEach(function (id) { body.append('include_contact_ids[]', id); });
            if (includeAllContacts) { body.append('include_all_contacts', '1'); }
            fetch(previewUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                countEl.textContent = data.count;
                if (data.members.length === 0) {
                    listEl.innerHTML = '<p class="text-muted">{{ __('No one matches this combination.') }}</p>';
                    return;
                }
                listEl.innerHTML = '<ul class="list-unstyled mb-0">' + data.members.map(function (m) {
                    return '<li>' + m.name + ' <span class="text-muted">(' + m.email + ')</span></li>';
                }).join('') + (data.truncated ? '<li class="text-muted">{{ __('…and more') }}</li>' : '') + '</ul>';
            });
        }

        if (criteriaBox) {
            criteriaBox.addEventListener('change', function (e) {
                if (!e.target.matches('[data-criterion]')) { return; }
                clearTimeout(timer);
                timer = setTimeout(runPreview, 300);
            });
        }

        if (includeAllContactsInput) {
            includeAllContactsInput.addEventListener('change', function () {
                clearTimeout(timer);
                timer = setTimeout(runPreview, 300);
            });
        }

        @if($isBureauMaster)
        const form = document.getElementById('aliasBuilderForm');
        const tagInput = document.getElementById('builderTags');
        const labelInput = document.getElementById('builderLabel');
        const authInput = document.getElementById('builderAuthLevel');
        const methodInput = document.getElementById('builderMethod');
        const storeUrl = document.getElementById('builderActionStore').value;
        const cancelBtn = document.getElementById('builderCancelEdit');

        function resetForm() {
            form.action = storeUrl;
            methodInput.value = 'POST';
            tagInput.value = '';
            labelInput.value = '';
            authInput.value = 'bureau';
            criteriaBox.querySelectorAll('[data-criterion]').forEach(function (el) { el.checked = false; });
            setChips('include', []);
            setChips('exclude', []);
            setChips('contact', []);
            if (includeAllContactsInput) { includeAllContactsInput.checked = false; }
            cancelBtn.classList.add('d-none');
            runPreview();
        }

        document.querySelectorAll('[data-edit-definition]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const row = btn.closest('[data-definition]');
                form.action = row.dataset.updateUrl;
                methodInput.value = 'PUT';
                tagInput.value = row.dataset.tags;
                labelInput.value = row.dataset.label;
                authInput.value = row.dataset.authLevel;
                const criteria = JSON.parse(row.dataset.criteria || '[]');
                criteriaBox.querySelectorAll('[data-criterion]').forEach(function (el) {
                    el.checked = criteria.includes(el.value);
                });
                setChips('include', JSON.parse(row.dataset.includeIds || '[]'));
                setChips('exclude', JSON.parse(row.dataset.excludeIds || '[]'));
                setChips('contact', JSON.parse(row.dataset.contactIds || '[]'));
                if (includeAllContactsInput) { includeAllContactsInput.checked = row.dataset.includeAllContacts === '1'; }
                cancelBtn.classList.remove('d-none');
                runPreview();
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
        cancelBtn.addEventListener('click', resetForm);
        @endif

        document.querySelectorAll('[data-confirm-form]').forEach(function (f) {
            f.addEventListener('submit', function (e) {
                if (!window.confirm(f.dataset.confirm || '{{ __('Are you sure?') }}')) { e.preventDefault(); }
            });
        });
    })();
    </script>
    @endpush
</x-admin-layout>
