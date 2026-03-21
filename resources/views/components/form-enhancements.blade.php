{{-- Shared date/time pickers (Flatpickr), input masks, IBAN formatter --}}
@once
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
@endpush
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// === Flatpickr: auto-upgrade all date/time inputs ===
document.querySelectorAll('input[type="date"]').forEach(el => {
    el.type = 'text';
    flatpickr(el, {dateFormat:'Y-m-d', allowInput:true, defaultDate: el.value || null, minDate: el.min || null});
});
document.querySelectorAll('input[type="time"], [data-picker="time"]').forEach(el => {
    el.type = 'text';
    flatpickr(el, {enableTime:true, noCalendar:true, dateFormat:'H:i', time_24hr:true, allowInput:true, defaultDate: el.value || null});
});
document.querySelectorAll('[data-picker="date"]').forEach(el => {
    if (!el._flatpickr) flatpickr(el, {dateFormat:'Y-m-d', allowInput:true});
});
document.querySelectorAll('[data-picker="daterange"]').forEach(el => {
    const startInput = document.querySelector(el.dataset.rangeStart);
    const endInput = document.querySelector(el.dataset.rangeEnd);
    flatpickr(el, {mode:'range', dateFormat:'Y-m-d', allowInput:true,
        onChange(dates) {
            if (dates.length === 2 && startInput && endInput) {
                startInput.value = flatpickr.formatDate(dates[0], 'Y-m-d');
                endInput.value = flatpickr.formatDate(dates[1], 'Y-m-d');
            }
        }
    });
});

// === Input masks (generic pattern: # = digit, A = letter, X = any) ===
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

// === IBAN formatter (auto-space every 4 chars) ===
document.querySelectorAll('[data-mask="iban"]').forEach(el => {
    el.addEventListener('input', function() {
        let v = this.value.replace(/\s/g, '').toUpperCase().replace(/[^A-Z0-9]/g, '');
        this.value = v.replace(/(.{4})/g, '$1 ').trim();
    });
});

// === Phone formatter (auto +prefix, spaces) ===
document.querySelectorAll('input[type="tel"]').forEach(el => {
    el.addEventListener('blur', function() {
        let v = this.value.replace(/[^\d+]/g, '');
        if (v && !v.startsWith('+')) v = '+' + v;
        this.value = v.replace(/(\+\d{3})(\d{3})(\d{3})(\d*)/, '$1 $2 $3 $4').trim();
    });
});
</script>
@endpush
@endonce
