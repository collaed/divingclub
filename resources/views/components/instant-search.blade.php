{{--
    Instant search filter for tables.
    Add data-searchable to the table, and this script filters rows on keyup.
    The search input must have data-instant-search="tableId".
    Falls back to form submit on Enter for server-side search + pagination.
--}}
@once
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-instant-search]').forEach(function(input) {
        var tableId = input.dataset.instantSearch;
        var table = document.getElementById(tableId);
        if (!table) return;
        var rows = table.querySelectorAll('tbody tr');

        input.addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                row.style.display = (!q || text.includes(q)) ? '' : 'none';
            });
        });
    });
});
</script>
@endonce
