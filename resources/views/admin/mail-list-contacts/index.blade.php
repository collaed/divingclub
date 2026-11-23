<x-admin-layout :title="__('Mailing List Contacts')">
    @php($isBureauMaster = auth()->user()->hasRole('bureau_master'))

    <h4 class="mb-1">{{ __('Mailing List Contacts') }}</h4>
    <p class="text-muted small mb-4">{{ __('People who have expressed interest in the club but never registered an account. Pick this bucket into any mail alias (see :link) to reach them too — they can unsubscribe themselves at any time via a link added to their copy of the message.', ['link' => __('Mail Aliases')]) }}</p>

    <div class="card dc-card">
        <div class="card-header">{{ __('Contacts') }}</div>
        <div class="card-body p-0" id="contactsSection" data-store-url="{{ route('admin.mail-list-contacts.store') }}">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('Email') }}</th><th>{{ __('Name') }}</th><th>{{ __('Status') }}</th><th>{{ __('Added') }}</th>@if($isBureauMaster)<th class="text-end">{{ __('Actions') }}</th>@endif</tr></thead>
                <tbody data-contacts-body>
                @include('admin.mail-list-contacts._rows', ['contacts' => $contacts, 'isBureauMaster' => $isBureauMaster])
                </tbody>
            </table>
        </div>
    </div>

    @if($isBureauMaster)
    <div class="card dc-card mt-3">
        <div class="card-header">{{ __('Add contacts') }}</div>
        <div class="card-body">
            <form id="bulkAddForm">
                <div class="mb-2">
                    <textarea name="entries" class="form-control form-control-sm" rows="5" placeholder="{{ __("jane@example.com\nJohn Doe <john@example.com>") }}" required></textarea>
                    <div class="form-text">{{ __('One per line: either a bare email, or "Name <email>".') }}</div>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Add') }}</button>
                <span class="small text-muted ms-2" data-bulk-result></span>
            </form>
        </div>
    </div>
    @endif

    @push('scripts')
    <script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const section = document.getElementById('contactsSection');
        if (!section || !csrf) { return; }

        section.addEventListener('click', function (e) {
            const toggleBtn = e.target.closest('[data-contact-toggle]');
            const deleteBtn = e.target.closest('[data-contact-delete]');

            if (toggleBtn) {
                toggleBtn.disabled = true;
                fetch(toggleBtn.dataset.url, { method: 'PATCH', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        const row = toggleBtn.closest('[data-contact-row]');
                        const badge = row.querySelector('[data-contact-status]');
                        badge.className = 'badge ' + (data.subscribed ? 'bg-success' : 'bg-secondary');
                        badge.textContent = data.subscribed ? '{{ __('Subscribed') }}' : '{{ __('Unsubscribed') }}';
                        toggleBtn.textContent = data.subscribed ? '{{ __('Unsubscribe') }}' : '{{ __('Resubscribe') }}';
                        toggleBtn.disabled = false;
                    })
                    .catch(function () { toggleBtn.disabled = false; });
            }

            if (deleteBtn) {
                if (!window.confirm(deleteBtn.dataset.confirm || '{{ __('Remove this contact?') }}')) { return; }
                deleteBtn.disabled = true;
                fetch(deleteBtn.dataset.url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function () { deleteBtn.closest('[data-contact-row]').remove(); })
                    .catch(function () { deleteBtn.disabled = false; });
            }
        });

        const bulkForm = document.getElementById('bulkAddForm');
        if (bulkForm) {
            bulkForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const body = new URLSearchParams(new FormData(bulkForm));
                fetch(section.dataset.storeUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    bulkForm.querySelector('[data-bulk-result]').textContent = '{{ __('Added') }}: ' + data.added + ', {{ __('Skipped') }}: ' + data.skipped;
                    bulkForm.reset();
                    section.querySelector('[data-contacts-body]').innerHTML = data.html;
                });
            });
        }
    })();
    </script>
    @endpush
</x-admin-layout>
