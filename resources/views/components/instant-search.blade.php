{{--
    Instant search filter for tables.
    Add data-searchable to the table, and this script filters rows on keyup.
    The search input must have data-instant-search="tableId".
    Instant JS filtering for current page rows + debounced form submit for full backend filtering.
--}}
@once
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-instant-search]').forEach(function(input) {
        var tableId = input.dataset.instantSearch;
        var table = document.getElementById(tableId);
        if (!table) return;
        var rows = table.querySelectorAll('tbody tr');
        var debounceTimer = null;
        var lastSubmitted = input.value;

        input.addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                row.style.display = (!q || text.includes(q)) ? '' : 'none';
            });

            // Debounced backend submit for full filtering across all pages
            clearTimeout(debounceTimer);
            var currentValue = this.value;
            debounceTimer = setTimeout(function() {
                if (currentValue !== lastSubmitted) {
                    lastSubmitted = currentValue;
                    input.form.submit();
                }
            }, 600);
        });
    });
});
</script>
@endonce
