{{-- Makes table rows clickable. Add data-href="url" to <tr> elements. --}}
@once
<style>
tr[data-href] { cursor: pointer; }
tr[data-href]:hover { background-color: rgba(0,0,0,.04) !important; }
</style>
<script>
document.addEventListener('click', function(e) {
    var tr = e.target.closest('tr[data-href]');
    if (!tr) return;
    // Don't navigate if clicking a button, link, input, or form element
    if (e.target.closest('a, button, input, select, form, .btn')) return;
    window.location = tr.dataset.href;
});
</script>
@endonce
