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

    async function send(row, extra) {
        const body = new FormData();
        body.append('season_year', row.dataset.year);
        Object.entries(extra).forEach(([k, v]) => body.append(k, v));
        row.querySelectorAll('button').forEach((b) => (b.disabled = true));

        let res;
        let data = {};
        try {
            res = await fetch(row.dataset.url, {
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
