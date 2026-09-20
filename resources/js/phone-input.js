/**
 * Phone number mask for [data-phone] inputs.
 *
 * Progressive enhancement: the original input keeps its name and is submitted
 * as one canonical value ("+352 621 123 456"); a country selector and a
 * national-number field are shown in its place. Typing or pasting a full
 * number ("00 33 6 12 34 56 78", "+32 471 ...") picks the country from its
 * code. Countries are listed by how often the club's members use them.
 *
 * No inline handlers — the component is built and wired from here.
 */
(function () {
    'use strict';

    // [dial code, ISO region, trunk "0" dropped, group sizes for the national number]
    const COUNTRIES = [
        ['352', 'LU', false, [3, 3, 3]],
        ['33', 'FR', true, [1, 2, 2, 2, 2]],
        ['32', 'BE', true, [3, 2, 2, 2]],
        ['49', 'DE', true, [3, 4, 4]],
        ['358', 'FI', true, [2, 3, 4]],
        ['34', 'ES', false, [3, 3, 3]],
        ['30', 'GR', false, [3, 3, 4]],
        ['36', 'HU', true, [2, 3, 4]],
        ['39', 'IT', false, [3, 3, 4]],
        ['31', 'NL', true, [1, 3, 3, 2]],
        ['44', 'GB', true, [4, 3, 3]],
        ['41', 'CH', true, [2, 3, 2, 2]],
        ['351', 'PT', false, [3, 3, 3]],
        ['43', 'AT', true, [3, 4, 4]],
        ['48', 'PL', false, [3, 3, 3]],
        ['40', 'RO', true, [3, 3, 3]],
        ['46', 'SE', true, [2, 3, 2, 2]],
        ['45', 'DK', false, [2, 2, 2, 2]],
        ['47', 'NO', false, [3, 2, 3]],
        ['1', 'US', false, [3, 3, 4]],
    ];
    const DEFAULT_CODE = '352';
    const BY_CODE = Object.fromEntries(COUNTRIES.map((c) => [c[0], c]));

    function flag(region) {
        return String.fromCodePoint(...[...region].map((ch) => 0x1f1e6 + ch.charCodeAt(0) - 65));
    }

    function regionName(region) {
        try {
            return new Intl.DisplayNames([document.documentElement.lang || 'en'], { type: 'region' }).of(region);
        } catch (e) {
            return region;
        }
    }

    /** Longest dial code the digits start with, or null. */
    function matchCode(digits) {
        for (const len of [3, 2, 1]) {
            const code = digits.slice(0, len);
            if (BY_CODE[code]) {
                return code;
            }
        }
        return null;
    }

    function group(national, sizes) {
        const parts = [];
        let rest = national;
        for (const size of sizes) {
            if (!rest) {
                break;
            }
            parts.push(rest.slice(0, size));
            rest = rest.slice(size);
        }
        if (rest) {
            parts.push(rest);
        }
        return parts.join(' ');
    }

    /** Split a raw string into [code, national digits]; code is null when none is typed. */
    function parse(raw) {
        let value = raw.trim();
        if (value.startsWith('00')) {
            value = '+' + value.slice(2);
        }
        if (value.startsWith('+')) {
            const digits = value.replace(/\D/g, '');
            const code = matchCode(digits);
            return code ? [code, digits.slice(code.length)] : [null, digits];
        }
        return [null, value.replace(/\D/g, '')];
    }

    function build(hidden) {
        const wrap = document.createElement('div');
        wrap.className = 'input-group phone-mask';

        const select = document.createElement('select');
        select.className = 'form-select flex-grow-0';
        select.style.width = 'auto';
        select.setAttribute('aria-label', hidden.dataset.phoneCountryLabel || 'Country code');
        COUNTRIES.forEach(([code, region]) => {
            const opt = document.createElement('option');
            opt.value = code;
            opt.textContent = flag(region) + ' +' + code;
            opt.title = regionName(region);
            select.appendChild(opt);
        });

        const field = document.createElement('input');
        field.type = 'tel';
        field.inputMode = 'tel';
        field.autocomplete = 'tel-national';
        field.className = 'form-control';
        field.placeholder = hidden.dataset.phonePlaceholder || '621 123 456';
        if (hidden.hasAttribute('required')) {
            field.required = true;
        }
        if (hidden.id) {
            field.id = hidden.id;
            hidden.removeAttribute('id');
        }
        if (hidden.classList.contains('is-invalid')) {
            field.classList.add('is-invalid');
            select.classList.add('is-invalid');
        }

        wrap.append(select, field);
        hidden.type = 'hidden';
        hidden.removeAttribute('required');
        hidden.classList.remove('is-invalid');
        hidden.after(wrap);

        // Keep the server's invalid-feedback visible: it is no longer a sibling of the input.
        const feedback = wrap.nextElementSibling;
        if (feedback && feedback.classList.contains('invalid-feedback') && field.classList.contains('is-invalid')) {
            feedback.classList.add('d-block');
        }

        return { select, field };
    }

    function enhance(hidden) {
        const { select, field } = build(hidden);
        const original = hidden.value;

        function commit() {
            const digits = field.value.replace(/\D/g, '');
            hidden.value = digits ? '+' + select.value + ' ' + field.value : '';
        }

        function format() {
            const [code, national] = parse(field.value);
            if (code) {
                select.value = code;
            }
            const country = BY_CODE[select.value];
            const digits = country[2] && national.startsWith('0') ? national.slice(1) : national;
            field.value = group(digits, country[3]);
            commit();
        }

        // Show the stored value; the submitted value stays untouched until the user edits it.
        const [code, national] = parse(original);
        select.value = code || DEFAULT_CODE;
        const country = BY_CODE[select.value];
        field.value = original === ''
            ? ''
            : code
                ? group(national, country[3])
                : original;
        if (original !== '' && !code) {
            hidden.value = original;
        }

        field.addEventListener('input', format);
        select.addEventListener('change', format);
        hidden.form?.addEventListener('submit', () => {
            if (field.dataset.touched || hidden.value === '') {
                commit();
            }
        });
        field.addEventListener('input', () => {
            field.dataset.touched = '1';
        });
    }

    function init() {
        document.querySelectorAll('input[data-phone]').forEach(enhance);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
