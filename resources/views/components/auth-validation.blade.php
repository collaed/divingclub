<script>
document.addEventListener('DOMContentLoaded', function() {
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
