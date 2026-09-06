# In-Place AJAX Updates (No Full Reload)

All admin sub-settings pages, management tables, and any page with per-row or
per-section editable fields MUST update in place with a silent AJAX call. The
user's context — scroll position, which accordion/tab is open, which row they
were editing — must never be lost to a full page re-request.

This is the default expectation for **new** code. For **existing** pages, bring
them into compliance **only when you are already touching them** for a feature
or fix — there is no need to sweep every page at once. When you do touch such a
page, convert its save/delete/toggle interactions to the pattern below.

## Server side (controllers)

- Detect an AJAX/JSON client with **`$request->expectsJson()`**, never
  `$request->ajax()`. `ajax()` only checks the `X-Requested-With` header, which
  `fetch()` clients do not send; `expectsJson()` also honours
  `Accept: application/json`.
- Return JSON for AJAX and redirect for non-AJAX (graceful degradation):

  ```php
  public function updateThing(Request $request, Thing $thing): RedirectResponse|JsonResponse
  {
      $data = $request->validate([...]);
      $thing->update($data);

      if ($request->expectsJson()) {
          return response()->json(['ok' => true, 'thing' => $thing->fresh()]);
      }

      return back()->with('success', __('Saved.'));
  }
  ```

- On a **blocked/invalid** action return HTTP `422` with `['ok' => false,
  'message' => __('...')]` so the client can surface the reason. Validation
  failures already return `422` for JSON requests automatically.
- Keep the response payload small and specific (the updated record, or just
  `{"ok": true}`), not the whole page.
- Method routing: a `PUT`/`PATCH`/`DELETE` route needs a matching `fetch`
  `method`. Do **not** rely on `_method` spoofing inside a JSON body — Laravel
  reads `_method` from form fields only. Either send the real verb
  (`method: 'PUT'`) or send form data with a `_method` field.

## Client side (blade + vanilla JS)

- No inline `onclick`. Use `data-*` attributes + a single delegated listener on
  a stable container element (project rule).
- Debounce field edits (~300–400 ms) before saving; save immediately on
  explicit actions (delete, toggle).
- Send `Accept: application/json`, `X-CSRF-TOKEN`, and (for bodies)
  `Content-Type: application/json`.
- On success: patch the DOM in place (update text, remove the row, flip a
  toggle) and show a brief confirmation via `showToast(message, type)` or a
  small inline "✓ Saved" indicator. On failure: re-enable controls and
  `showToast(message, 'danger')`.
- Preserve focus/caret when replacing a region the user may be typing in.

### Reference implementations in this repo

- `resources/views/admin/settings/index.blade.php` — Member Statuses section:
  per-row debounced auto-save (`data-status-field`), AJAX delete
  (`data-status-delete`) that removes the row, plus the Status Sets section
  (`js-set-status` / `js-set-default`). This is the canonical pattern.
- `resources/js/dues-live.js` — region-swap pattern: posts the form, parses the
  HTML response, and swaps a `[data-...-body]` block while preserving focus.

### Minimal client template

```html
<div id="thingRegion" data-thing-region>
    <div data-thing-row data-url="{{ route('admin.things.update', $thing) }}">
        <input type="text" value="{{ $thing->name }}" data-thing-field="name">
        <button type="button" data-thing-delete
                data-url="{{ route('admin.things.destroy', $thing) }}"
                data-confirm="{{ __('Delete “:n”?', ['n' => $thing->name]) }}">🗑️</button>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const region = document.getElementById('thingRegion');
    if (!region || !csrf) { return; }
    let timer = null;

    region.addEventListener('input', function (e) {
        const field = e.target.closest('[data-thing-field]');
        if (!field) { return; }
        const row = field.closest('[data-thing-row]');
        clearTimeout(timer);
        timer = setTimeout(function () {
            fetch(row.dataset.url, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ name: row.querySelector('[data-thing-field="name"]').value }),
            }).then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } })
              .catch(function () { if (typeof showToast === 'function') { showToast('{{ __('Save failed') }}', 'danger'); } });
        }, 350);
    });

    region.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-thing-delete]');
        if (!btn) { return; }
        if (!window.confirm(btn.dataset.confirm || 'Delete?')) { return; }
        btn.disabled = true;
        fetch(btn.dataset.url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
            .then(async function (r) {
                const data = await r.json().catch(function () { return {}; });
                if (!r.ok || !data.ok) { throw new Error(data.message || ('HTTP ' + r.status)); }
                btn.closest('[data-thing-row]').remove();
                if (typeof showToast === 'function') { showToast('{{ __('Deleted.') }}', 'success'); }
            })
            .catch(function (err) { btn.disabled = false; if (typeof showToast === 'function') { showToast(err.message, 'danger'); } });
    });
})();
</script>
@endpush
```

## Do NOT

- Do not full-`redirect()`/reload for a single field edit, toggle, or row delete
  on a settings/management page.
- Do not use `$request->ajax()` for JSON detection.
- Do not wrap a `<form>` across multiple `<td>` cells to get an inline row form
  (invalid HTML). Use per-field `data-*` + AJAX instead.
- Do not lose the open accordion/tab or scroll position; never re-render the
  whole page for a local change.
