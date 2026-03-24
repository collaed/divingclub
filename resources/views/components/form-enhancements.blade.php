{{-- Shared date/time pickers (Flatpickr), input masks, IBAN formatter --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.querySelectorAll('input[type="date"]').forEach(el => {
    el.type = 'text';
    flatpickr(el, {dateFormat:'Y-m-d', allowInput:true, defaultDate: el.value || null, minDate: el.min || null});
});
document.querySelectorAll('input[type="time"], [data-picker="time"]').forEach(el => {
    el.type = 'text';
    flatpickr(el, {enableTime:true, noCalendar:true, dateFormat:'H:i', time_24hr:true, allowInput:true, defaultDate: el.value || null});
});

// === Input masks ===
document.querySelectorAll('[data-mask]:not([data-mask="iban"])').forEach(el => {
    const mask = el.dataset.mask;
    el.addEventListener('input', function() {
        let v = this.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
        let result = '', vi = 0;
        for (let i = 0; i < mask.length && vi < v.length; i++) {
            const m = mask[i];
            if (m === '#') { if (/\d/.test(v[vi])) result += v[vi++]; else vi++; }
            else if (m === 'A') { if (/[A-Z]/.test(v[vi])) result += v[vi++]; else vi++; }
            else if (m === 'X') { result += v[vi++]; }
            else { result += m; if (v[vi] === m) vi++; }
        }
        this.value = result;
    });
});

// === IBAN formatter ===
document.querySelectorAll('[data-mask="iban"]').forEach(el => {
    el.addEventListener('input', function() {
        let v = this.value.replace(/\s/g, '').toUpperCase().replace(/[^A-Z0-9]/g, '');
        this.value = v.replace(/(.{4})/g, '$1 ').trim();
    });
});

// === Phone formatter ===
document.querySelectorAll('input[type="tel"]').forEach(el => {
    el.addEventListener('blur', function() {
        let v = this.value.replace(/[^\d+]/g, '');
        if (v && !v.startsWith('+')) v = '+' + v;
        this.value = v.replace(/(\+\d{3})(\d{3})(\d{3})(\d*)/, '$1 $2 $3 $4').trim();
    });
});
</script>
