/**
 * DivingClub — Shared table utilities
 * Auto-initializes on DOMContentLoaded for any page with tables.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Instant search: filter table rows by input value
    document.querySelectorAll('[data-instant-search]').forEach(function(input) {
        var tableId = input.dataset.instantSearch;
        var table = document.getElementById(tableId);
        if (!table) return;
        var tbody = table.querySelector('tbody');
        input.addEventListener('input', function() {
            var q = this.value.toLowerCase();
            tbody.querySelectorAll('tr').forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    });

    // Clickable rows: navigate on row click (skip buttons/links/forms)
    document.addEventListener('click', function(e) {
        var tr = e.target.closest('tr[data-href]');
        if (!tr) return;
        if (e.target.closest('a, button, input, select, form, .btn, .dropdown')) return;
        window.location = tr.dataset.href;
    });

    // Client-side column sort
    document.querySelectorAll('th[data-sort-col]').forEach(function(th) {
        th.style.cursor = 'pointer';
        var dir = 0;
        var ci = Array.from(th.parentElement.children).indexOf(th);
        th.addEventListener('click', function() {
            var tbody = th.closest('table').querySelector('tbody');
            if (!tbody) return;
            var rows = Array.from(tbody.querySelectorAll('tr'));
            th.parentElement.querySelectorAll('th').forEach(function(s) { if (s !== th) s.dataset.sort = ''; });
            dir = dir === 1 ? -1 : 1;
            th.dataset.sort = dir === 1 ? 'asc' : 'desc';
            rows.sort(function(a, b) {
                var ac = (a.children[ci] || {}).textContent || '';
                var bc = (b.children[ci] || {}).textContent || '';
                var an = parseFloat(ac.replace(/[^\d.,-]/g, '')), bn = parseFloat(bc.replace(/[^\d.,-]/g, ''));
                if (!isNaN(an) && !isNaN(bn)) return (an - bn) * dir;
                return ac.localeCompare(bc, undefined, { sensitivity: 'base' }) * dir;
            });
            rows.forEach(function(r) { tbody.appendChild(r); });
        });
    });
});
