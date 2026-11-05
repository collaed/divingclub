/**
 * Membership renewals screen: mark a member's season as paid in place.
 *
 * "Received" confirms the proposed amount; "Check" sends the typed amount and
 * the server says which option it matches. When it matches several insurance
 * options the bureau picks one; when it matches none the reason is shown and
 * nothing is marked. Event delegation, no inline handlers.
 */
(function () {
    'use strict';

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    function message(row, text, ok) {
        const box = row.querySelector('[data-renewal-message]');
        box.className = 'small mt-1 ' + (ok ? 'text-success' : 'text-danger');
        box.textContent = text;
        return box;
    }

    async function send(row, extra, action) {
        const body = new FormData();
        body.append('season_year', row.dataset.year);
        Object.entries(extra).forEach(([k, v]) => body.append(k, v));
        row.querySelectorAll('button').forEach((b) => (b.disabled = true));

        let res;
        let data = {};
        try {
            res = await fetch(action === 'override' ? row.dataset.overrideUrl : row.dataset.url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });
            data = await res.json();
        } catch (e) {
            message(row, 'Network error — nothing was saved.', false);
            row.querySelectorAll('button').forEach((b) => (b.disabled = false));
            return;
        }

        if (res.ok && data.ok) {
            row.classList.add('table-success');
            row.querySelector('[data-renewal-actions]').remove();
            message(row, data.message, true);
            const counter = document.querySelector('[data-renewal-count]');
            if (counter) {
                counter.textContent = String(Math.max(0, parseInt(counter.textContent, 10) - 1));
            }
            return;
        }

        row.querySelectorAll('button').forEach((b) => (b.disabled = false));
        const box = message(row, data.message || 'Could not save.', false);
        if (!data.ambiguous && res.status === 422) {
            const open = document.createElement('button');
            open.type = 'button';
            open.className = 'btn btn-sm btn-outline-secondary ms-2';
            open.dataset.renewalOpenOverride = '1';
            open.textContent = 'Record it anyway…';
            box.appendChild(open);
        }
        if (data.ambiguous) {
            (data.matches || []).forEach((m) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-outline-secondary ms-2';
                btn.dataset.renewalPick = m.insurance || '';
                btn.textContent = m.label;
                box.appendChild(btn);
            });
        }
    }

    function typedAmount(row) {
        return row.querySelector('[data-renewal-amount]').value;
    }

    document.addEventListener('click', (e) => {
        const row = e.target.closest('[data-renewal-row]');
        if (!row) {
            return;
        }
        if (e.target.closest('[data-renewal-received]')) {
            send(row, {});
        } else if (e.target.closest('[data-renewal-check]')) {
            const amount = typedAmount(row);
            if (amount !== '') {
                send(row, { amount });
            }
        } else if (e.target.closest('[data-renewal-override]')) {
            const amount = row.querySelector('[data-renewal-override-amount]').value;
            send(row, {
                method: row.querySelector('[data-renewal-method]').value,
                note: row.querySelector('[data-renewal-note]').value,
                ...(amount !== '' ? { amount } : {}),
            }, 'override');
        } else if (e.target.closest('[data-renewal-open-override]')) {
            row.querySelector('[data-renewal-override-box]').open = true;
            row.querySelector('[data-renewal-override-amount]').value = typedAmount(row);
            row.querySelector('[data-renewal-note]').focus();
        } else if (e.target.closest('[data-renewal-pick]')) {
            send(row, { amount: typedAmount(row), insurance: e.target.closest('[data-renewal-pick]').dataset.renewalPick });
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.matches('[data-renewal-amount]')) {
            e.preventDefault();
            e.target.closest('[data-renewal-row]').querySelector('[data-renewal-check]').click();
        }
    });
})();

/**
 * Licence renewal queue: select several members at once and clear one
 * federation's "request pending" flag for all of them in a single request.
 */
(function () {
    'use strict';

    const table = document.getElementById('table-licence-renewals');
    if (!table) {
        return;
    }

    const selectAll = document.querySelector('[data-licence-select-all]');
    const clearButtons = document.querySelectorAll('[data-licence-clear]');
    const countLabel = document.querySelector('[data-licence-selected-count]');

    function checkboxes() {
        return Array.from(table.querySelectorAll('[data-licence-select]'));
    }

    function refreshToolbar() {
        const checked = checkboxes().filter((c) => c.checked);
        clearButtons.forEach((btn) => (btn.disabled = checked.length === 0));
        if (countLabel) {
            countLabel.textContent = checked.length === 0
                ? countLabel.dataset.emptyLabel || countLabel.textContent
                : checked.length + ' selected';
        }
        if (selectAll) {
            const all = checkboxes();
            selectAll.checked = all.length > 0 && checked.length === all.length;
        }
    }

    if (countLabel) {
        countLabel.dataset.emptyLabel = countLabel.textContent;
    }

    table.addEventListener('change', (e) => {
        if (e.target.matches('[data-licence-select]')) {
            refreshToolbar();
        }
    });

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            checkboxes().forEach((c) => (c.checked = selectAll.checked));
            refreshToolbar();
        });
    }

    clearButtons.forEach((btn) => {
        btn.addEventListener('click', async () => {
            const federation = btn.dataset.licenceClear;
            const userIds = checkboxes().filter((c) => c.checked).map((c) => c.value);
            if (userIds.length === 0) {
                return;
            }

            clearButtons.forEach((b) => (b.disabled = true));
            const body = new FormData();
            userIds.forEach((id) => body.append('user_ids[]', id));
            body.append('federation', federation);

            try {
                const res = await fetch(btn.dataset.clearUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body,
                });
                if (!res.ok) {
                    throw new Error('failed');
                }
            } catch (e) {
                window.showToast?.('Network error — nothing was cleared.', 'error');
                refreshToolbar();
                return;
            }

            userIds.forEach((id) => {
                const row = table.querySelector('[data-licence-row][data-user-id="' + id + '"]');
                if (!row) {
                    return;
                }
                const badge = row.querySelector('[data-pending-fed="' + federation + '"]');
                badge?.remove();
                if (!row.querySelector('[data-pending-fed]')) {
                    row.remove();
                }
            });
            window.showToast?.(federation + ' marked done.', 'success');
            refreshToolbar();
        });
    });
})();

/**
 * Insurance backlog: tick a payment once its insurance is registered.
 */
(function () {
    'use strict';

    document.addEventListener('change', async (e) => {
        const box = e.target.closest('[data-insurance-toggle]');
        const row = box?.closest('[data-insurance-row]');
        if (!row) {
            return;
        }
        const body = new FormData();
        body.append('registered', box.checked ? '1' : '0');
        try {
            const res = await fetch(row.dataset.url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
            });
            if (!res.ok) {
                throw new Error('failed');
            }
            row.querySelector('[data-insurance-label]').textContent = box.checked
                ? new Date().toLocaleDateString('fr-FR')
                : 'Registered';
            row.classList.toggle('table-success', box.checked);
        } catch (err) {
            box.checked = !box.checked;
        }
    });
})();
