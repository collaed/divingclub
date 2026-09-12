<style>
    .dc-pw-wrap { position: relative; }
    .dc-pw-wrap > input { padding-right: 2.6rem; }
    .dc-pw-eye {
        position: absolute; top: 0; right: 0; height: calc(1.5em + 0.75rem + 2px);
        width: 2.5rem; display: flex; align-items: center; justify-content: center;
        background: none; border: 0; padding: 0; cursor: pointer;
        color: var(--bs-secondary-color, #6c757d);
    }
    .dc-pw-eye:hover { color: var(--bs-body-color, #212529); }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide password — eye toggle inside the field
    var EYE = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>';
    var EYE_OFF = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        var wrap = document.createElement('div');
        wrap.className = 'dc-pw-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'dc-pw-eye';
        btn.setAttribute('aria-label', '{{ __("Show password") }}');
        btn.innerHTML = EYE;
        wrap.appendChild(btn);
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.innerHTML = show ? EYE_OFF : EYE;
            btn.setAttribute('aria-label', show ? '{{ __("Hide password") }}' : '{{ __("Show password") }}');
        });
    });

    // Email validation
    document.querySelectorAll('input[type="email"]').forEach(el => {
        const fb = document.createElement('div');
        fb.className = 'invalid-feedback';
        fb.textContent = '{{ __("Please enter a valid email address") }}';
        el.after(fb);
        el.addEventListener('input', function() {
            const valid = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(this.value);
            this.classList.toggle('is-invalid', this.value && !valid);
            this.classList.toggle('is-valid', this.value && valid);
        });
    });

    // Password rules display
    const pw = document.getElementById('password');
    if (pw && (pw.closest('form')?.querySelector('[name="password_confirmation"]') || pw.closest('form')?.action?.includes('register'))) {
        const rules = document.createElement('div');
        rules.className = 'small mt-1';
        rules.id = 'pw-rules';
        rules.innerHTML = '<span data-rule="len" class="text-muted">✗ {{ __("Min. 8 characters") }}</span><br>'
            + '<span data-rule="upper" class="text-muted">✗ {{ __("One uppercase letter") }}</span><br>'
            + '<span data-rule="lower" class="text-muted">✗ {{ __("One lowercase letter") }}</span><br>'
            + '<span data-rule="num" class="text-muted">✗ {{ __("One number") }}</span>';
        pw.after(rules);

        pw.addEventListener('input', function() {
            const v = this.value;
            check('len', v.length >= 8);
            check('upper', /[A-Z]/.test(v));
            check('lower', /[a-z]/.test(v));
            check('num', /\d/.test(v));
        });

        function check(rule, ok) {
            const el = rules.querySelector('[data-rule="'+rule+'"]');
            el.className = ok ? 'text-success' : 'text-muted';
            el.textContent = (ok ? '✓' : '✗') + ' ' + el.textContent.substring(2);
        }

        // Confirm match
        const pc = document.getElementById('password_confirmation');
        if (pc) {
            const matchFb = document.createElement('div');
            matchFb.className = 'invalid-feedback';
            matchFb.textContent = '{{ __("Passwords do not match") }}';
            pc.after(matchFb);
            pc.addEventListener('input', function() {
                const match = this.value === pw.value;
                this.classList.toggle('is-invalid', this.value && !match);
                this.classList.toggle('is-valid', this.value && match);
            });
        }
    }

    // Prevent submit if invalid
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const invalidEmail = form.querySelector('input[type="email"].is-invalid');
            const pc = form.querySelector('#password_confirmation');
            if (invalidEmail) { e.preventDefault(); invalidEmail.focus(); return; }
            if (pc && pc.classList.contains('is-invalid')) { e.preventDefault(); pc.focus(); return; }
        });
    });
});
</script>
