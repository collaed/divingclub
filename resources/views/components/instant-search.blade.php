{{--
    Instant search filter for tables.
    Add data-searchable to the table, and this script filters rows on keyup.
    The search input must have data-instant-search="tableId".
    Instant JS filtering for current page rows, then a debounced background
    refresh against the server for full filtering across all pages.

    The background refresh never touches the input element, so it never
    steals focus/cursor position while someone is typing — unlike a plain
    form submit, which reloads the whole page. When the same table also has
    a wrapper `<tableId>-results` around the table + pagination, the refresh
    swaps just that wrapper's content in place (no page reload at all). A
    page without that wrapper falls back to a normal submit, and the next
    page load restores focus to the search field so it isn't left stranded.

    Add data-instant-search-extra="some-id another-id" on the input to swap
    additional regions from the same response (e.g. summary cards elsewhere
    on the page that also depend on the filtered result set).

    Any other filter control in the same form (checkboxes, selects marked
    data-autosubmit) rides the same no-reload path instead of submitting
    the form directly.
--}}
@once
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-instant-search]').forEach(function(input) {
        var tableId = input.dataset.instantSearch;
        var table = document.getElementById(tableId);
        var form = input.form;
        if (!table || !form) return;

        var resultsWrap = document.getElementById(tableId + '-results');
        var extraIds = (input.dataset.instantSearchExtra || '').split(/\s+/).filter(Boolean);
        var rows = table.querySelectorAll('tbody tr');
        var debounceTimer = null;
        var lastSubmitted = input.value;
        var inFlight = null;

        function filterVisibleRows() {
            var q = input.value.toLowerCase().trim();
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                row.style.display = (!q || text.includes(q)) ? '' : 'none';
            });
        }

        function sync() {
            if (!resultsWrap) {
                // No AJAX target on this page: fall back to a real submit,
                // and ask the reloaded page to hand focus back afterwards.
                try { sessionStorage.setItem('instantSearchFocus', tableId); } catch (e) {}
                form.submit();
                return;
            }

            if (inFlight) inFlight.abort();
            var controller = new AbortController();
            inFlight = controller;

            var qs = new URLSearchParams(new FormData(form)).toString();
            var url = form.action + (qs ? '?' + qs : '');

            // Deliberately no X-Requested-With header: some index() actions
            // branch on $request->ajax() to return an incompatible partial
            // shape. A plain fetch() keeps every page returning its normal
            // full HTML, so the same #tableId-results extraction works
            // everywhere without per-page special-casing.
            fetch(url, { signal: controller.signal })
                .then(function(res) { return res.text(); })
                .then(function(html) {
                    var freshDoc = new DOMParser().parseFromString(html, 'text/html');
                    var fresh = freshDoc.getElementById(tableId + '-results');
                    if (!fresh) { form.submit(); return; }
                    resultsWrap.innerHTML = fresh.innerHTML;
                    extraIds.forEach(function(id) {
                        var current = document.getElementById(id);
                        var freshExtra = freshDoc.getElementById(id);
                        if (current && freshExtra) current.innerHTML = freshExtra.innerHTML;
                    });
                    history.replaceState(null, '', url);
                    var freshTable = document.getElementById(tableId);
                    rows = freshTable ? freshTable.querySelectorAll('tbody tr') : [];
                    // Pages with their own per-row JS (status dropdowns, etc.)
                    // can listen for this to re-init anything that doesn't
                    // already use document-level event delegation.
                    resultsWrap.dispatchEvent(new CustomEvent('instant-search:refreshed', { bubbles: true }));
                })
                .catch(function(e) { if (e.name !== 'AbortError') form.submit(); });
        }

        input.addEventListener('input', function() {
            filterVisibleRows();
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                if (input.value !== lastSubmitted) {
                    lastSubmitted = input.value;
                    sync();
                }
            }, 350);
        });

        form.querySelectorAll('[data-autosubmit]').forEach(function(el) {
            el.addEventListener('change', sync);
        });

        // Landed here after a fallback submit — give the field its focus
        // and cursor back instead of leaving them on the page at large.
        try {
            if (sessionStorage.getItem('instantSearchFocus') === tableId) {
                sessionStorage.removeItem('instantSearchFocus');
                input.focus();
                input.setSelectionRange(input.value.length, input.value.length);
            }
        } catch (e) {}
    });
});
</script>
@endonce
