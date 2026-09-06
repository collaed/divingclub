/**
 * Live recompute for the membership dues calculator (/dues).
 *
 * Progressive enhancement: the form still works with a normal submit when JS is
 * disabled. When enabled, any change to a [data-dues-input] control (status,
 * date of birth, assurance) re-posts the form in the background and swaps the
 * derived-licence block and the result table, so the total, communication, and
 * FFESSM/FLASSA derivation update without a full page reload.
 *
 * No inline handlers — event delegation on the document, per project JS rules.
 */
(function () {
    'use strict';

    const FORM_SELECTOR = '[data-dues-form]';
    const INPUT_SELECTOR = '[data-dues-input]';
    const DEBOUNCE_MS = 300;

    let timer = null;

    function debounce(fn) {
        return function () {
            clearTimeout(timer);
            timer = setTimeout(fn, DEBOUNCE_MS);
        };
    }

    function findForm() {
        return document.querySelector(FORM_SELECTOR);
    }

    async function recompute(form) {
        // A status must be chosen before a meaningful calculation.
        const status = form.querySelector('[name="status_id"]');
        if (status && status.value === '') {
            return;
        }

        const formData = new FormData(form);

        let html;
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                body: formData,
            });
            if (!res.ok) {
                return; // Validation errors etc. — leave the current view untouched.
            }
            html = await res.text();
        } catch (e) {
            return; // Network error — silent, the manual button still works.
        }

        applyResponse(html);
    }

    function applyResponse(html) {
        const parsed = new DOMParser().parseFromString(html, 'text/html');

        // Swap the whole calculator card body: it re-renders the derived licence
        // block, the breakdown, the communication, and the bank/commit region in
        // one consistent piece, preserving the user's current selections (the
        // server echoes them back).
        const fresh = parsed.querySelector('[data-dues-body]');
        const current = document.querySelector('[data-dues-body]');
        if (fresh && current) {
            preserveFocus(current, fresh);
            current.replaceWith(fresh);
        }
    }

    /**
     * Remember which control had focus (by name) and its caret, so replacing the
     * card body doesn't drop the user out of the field they were editing.
     */
    function preserveFocus(current, fresh) {
        const active = document.activeElement;
        if (!active || !current.contains(active) || !active.name) {
            return;
        }
        const target = fresh.querySelector('[name="' + CSS.escape(active.name) + '"]');
        if (!target) {
            return;
        }
        // Defer until the fresh node is in the DOM.
        requestAnimationFrame(() => {
            target.focus();
            if (typeof target.selectionStart === 'number' && typeof active.selectionStart === 'number') {
                try {
                    target.setSelectionRange(active.selectionStart, active.selectionEnd);
                } catch (e) {
                    /* not a text input — ignore */
                }
            }
        });
    }

    document.addEventListener('change', function (event) {
        if (!event.target.closest(INPUT_SELECTOR)) {
            return;
        }
        const form = findForm();
        if (form) {
            debounce(() => recompute(form))();
        }
    });
})();
