<x-admin-layout :title="__('Navigation')">
    <h4 class="mb-2">@icon('🧭') {{ __('Navigation') }}</h4>
    <p class="text-muted small mb-4">
        {{ __('These dropdowns make up the main site navbar (About, Calendar, Members, Resources, My Account). An item linking to a page syncs its label to that page\'s current title automatically, unless you set an explicit label here. The Home link, Try Diving, the Admin menu, and the footer are managed in code, not here.') }}
    </p>

    <div id="menuItemsRegion" data-menu-item-region>
        @foreach($menuItems as $dropdown)
            @include('admin.menu-items._dropdown', ['dropdown' => $dropdown])
        @endforeach

        <form class="row g-2 align-items-end mt-4" data-menu-item-add-dropdown-form data-url="{{ route('admin.menu-items.store') }}">
            <div class="col-auto">
                <input type="text" class="form-control form-control-sm" name="label" placeholder="{{ __('New dropdown label') }}" required aria-label="{{ __('New dropdown label') }}">
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" name="auth_required" aria-label="{{ __('Visible to') }}">
                    <option value="">{{ __('Everyone') }}</option>
                    <option value="1">{{ __('Logged-in only') }}</option>
                    <option value="0">{{ __('Guests only') }}</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">+ {{ __('Add dropdown') }}</button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const region = document.getElementById('menuItemsRegion');
        if (!region || !csrf) { return; }

        function collectPayload(container) {
            const payload = {};
            container.querySelectorAll('[data-menu-item-field]').forEach(function (el) {
                const key = el.dataset.menuItemField;
                if (el.tagName === 'SELECT' && el.multiple) {
                    payload[key] = Array.from(el.selectedOptions).map(function (o) { return o.value; });
                } else if (key === 'auth_required') {
                    payload[key] = el.value === '' ? null : el.value === '1';
                } else {
                    payload[key] = el.value;
                }
            });
            return payload;
        }

        function saveRow(row) {
            fetch(row.dataset.url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify(collectPayload(row)),
            })
            .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
            .then(function () {
                const badge = row.querySelector('[data-menu-item-saved]');
                if (badge) { badge.classList.remove('d-none'); setTimeout(function () { badge.classList.add('d-none'); }, 1500); }
            })
            .catch(function () { if (typeof showToast === 'function') { showToast('{{ __('Save failed') }}', 'danger'); } });
        }

        let timer = null;
        region.addEventListener('input', function (e) {
            const field = e.target.closest('[data-menu-item-field]');
            if (!field) { return; }
            const row = field.closest('[data-menu-item-row]');
            if (!row) { return; }
            clearTimeout(timer);
            timer = setTimeout(function () { saveRow(row); }, 400);
        });
        region.addEventListener('change', function (e) {
            const field = e.target.closest('[data-menu-item-field]');
            if (field && (field.tagName === 'SELECT')) {
                const row = field.closest('[data-menu-item-row]');
                if (row) { saveRow(row); }
            }

            // Add-item form: toggle which fields are shown based on type/link_type.
            const addField = e.target.closest('[data-add-field]');
            if (addField) {
                const form = addField.closest('form');
                const type = form.querySelector('[data-add-field="type"]').value;
                const linkTypeField = form.querySelector('[data-add-field="link_type"]');
                const linkType = linkTypeField ? linkTypeField.value : null;
                form.querySelectorAll('[data-add-group="link_type"]').forEach(function (el) {
                    el.classList.toggle('d-none', type !== 'link');
                });
                form.querySelectorAll('[data-add-group="article"]').forEach(function (el) {
                    el.classList.toggle('d-none', !(type === 'link' && linkType === 'article'));
                });
                form.querySelectorAll('[data-add-group="target"]').forEach(function (el) {
                    el.classList.toggle('d-none', !(type === 'link' && (linkType === 'route' || linkType === 'url')));
                });
            }
        });

        region.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-menu-item-delete]');
            if (!btn) { return; }
            e.preventDefault();
            if (!window.confirm(btn.dataset.confirm || 'Delete?')) { return; }

            btn.disabled = true;
            fetch(btn.dataset.url, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            })
            .then(async function (r) {
                const data = await r.json().catch(function () { return {}; });
                if (!r.ok || !data.ok) { throw new Error(data.message || ('HTTP ' + r.status)); }
                const container = btn.closest('[data-menu-item-row]') || btn.closest('[data-menu-item-dropdown]');
                if (container) { container.remove(); }
                if (typeof showToast === 'function') { showToast('{{ __('Deleted.') }}', 'success'); }
            })
            .catch(function (err) {
                btn.disabled = false;
                if (typeof showToast === 'function') { showToast(err.message || '{{ __('Delete failed') }}', 'danger'); }
            });
        });

        region.addEventListener('submit', function (e) {
            const addForm = e.target.closest('[data-menu-item-add-form]');
            const addDropdownForm = e.target.closest('[data-menu-item-add-dropdown-form]');
            if (!addForm && !addDropdownForm) { return; }
            e.preventDefault();
            const form = addForm || addDropdownForm;

            let payload;
            if (addForm) {
                payload = {
                    parent_id: form.dataset.parentId,
                    type: form.querySelector('[data-add-field="type"]').value,
                };
                if (payload.type === 'link') {
                    payload.link_type = form.querySelector('[data-add-field="link_type"]').value;
                    payload.icon = form.querySelector('[data-add-field="icon"]').value;
                    payload.label = form.querySelector('[data-add-field="label"]').value || null;
                    if (payload.link_type === 'article') {
                        payload.article_id = form.querySelector('[data-add-field="article_id"]').value;
                    } else {
                        payload.target = form.querySelector('[data-add-field="target"]').value;
                    }
                }
            } else {
                payload = {
                    type: 'dropdown',
                    label: form.querySelector('[name="label"]').value,
                    auth_required: form.querySelector('[name="auth_required"]').value || null,
                };
                if (payload.auth_required !== null) { payload.auth_required = payload.auth_required === '1'; }
            }

            fetch(form.dataset.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            })
            .then(async function (r) {
                const data = await r.json().catch(function () { return {}; });
                if (!r.ok || !data.ok) { throw new Error(data.message || ('HTTP ' + r.status)); }
                if (addForm) {
                    form.closest('[data-menu-item-dropdown]').querySelector('[data-menu-item-children]').insertAdjacentHTML('beforeend', data.html);
                    form.reset();
                    form.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    form.insertAdjacentHTML('beforebegin', data.html);
                    form.reset();
                }
                if (typeof showToast === 'function') { showToast('{{ __('Added.') }}', 'success'); }
            })
            .catch(function (err) {
                if (typeof showToast === 'function') { showToast(err.message || '{{ __('Add failed') }}', 'danger'); }
            });
        });
    })();
    </script>
    @endpush
</x-admin-layout>
