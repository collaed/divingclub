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
