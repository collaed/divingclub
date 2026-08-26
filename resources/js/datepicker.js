import flatpickr from 'flatpickr';

/**
 * Auto-initialize flatpickr on elements with [data-picker].
 *
 * Supported values for data-picker:
 *   "date"      — single date picker (default format Y-m-d)
 *   "daterange" — date range picker, populates hidden inputs via data-range-start / data-range-end
 *   "datetime"  — date + time picker
 *   "time"      — time-only picker
 *
 * Additional data attributes:
 *   data-min-date    — minimum selectable date
 *   data-max-date    — maximum selectable date
 *   data-default     — default date value
 *   data-format      — custom date format (flatpickr tokens)
 *   data-range-start — selector for hidden input receiving range start (daterange mode)
 *   data-range-end   — selector for hidden input receiving range end (daterange mode)
 */
function initDatepickers(root = document) {
    const elements = root.querySelectorAll('[data-picker]');

    elements.forEach((el) => {
        // Skip already-initialized elements
        if (el._flatpickr) return;

        const mode = el.dataset.picker || 'date';
        const config = buildConfig(el, mode);

        flatpickr(el, config);
    });
}

function buildConfig(el, mode) {
    const config = {
        allowInput: true,
        altInput: true,
        altFormat: 'd/m/Y',
        dateFormat: 'Y-m-d',
    };

    // Mode-specific settings
    switch (mode) {
        case 'daterange':
            config.mode = 'range';
            config.altFormat = 'd/m/Y';
            config.allowInput = false;
            config.onClose = function (selectedDates) {
                syncRangeInputs(el, selectedDates);
            };
            break;
        case 'datetime':
            config.enableTime = true;
            config.altFormat = 'd/m/Y H:i';
            config.dateFormat = 'Y-m-d H:i';
            break;
        case 'time':
            config.enableTime = true;
            config.noCalendar = true;
            config.altFormat = 'H:i';
            config.dateFormat = 'H:i';
            break;
        default: // "date"
            break;
    }

    // Optional data attributes
    if (el.dataset.minDate) config.minDate = el.dataset.minDate;
    if (el.dataset.maxDate) config.maxDate = el.dataset.maxDate;
    if (el.dataset.default) config.defaultDate = el.dataset.default;
    if (el.dataset.format) {
        config.dateFormat = el.dataset.format;
        config.altFormat = el.dataset.format;
    }

    return config;
}

function syncRangeInputs(el, selectedDates) {
    const startSelector = el.dataset.rangeStart;
    const endSelector = el.dataset.rangeEnd;

    if (!startSelector || !endSelector) return;

    const startInput = document.querySelector(startSelector);
    const endInput = document.querySelector(endSelector);

    if (startInput) {
        startInput.value = selectedDates[0] ? formatDate(selectedDates[0]) : '';
    }
    if (endInput) {
        endInput.value = selectedDates[1] ? formatDate(selectedDates[1]) : '';
    }
}

function formatDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

// Initialize on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => initDatepickers());

// Export for manual re-init (e.g. after AJAX-loaded content)
window.initDatepickers = initDatepickers;
