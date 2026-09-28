# SonarCloud Hygiene (Security Hotspots & Reliability Bugs)

This project runs SonarCloud on every PR (Quality Gate) and project-wide
(Security Hotspots, human-reviewed). A large backlog of pre-existing findings
was cleared as direct-to-`main` hotfixes in September 2026. These rules exist
so **no new occurrence of the same finding families is introduced**, and so
existing ones nearby get cleaned up opportunistically. This is the default
expectation for **new** code; for pre-existing files, fix the pattern **only
when you are already touching that file** for a feature or fix — no need to
sweep everything at once (same policy as `in-place-ajax.md`).

## Never use `rand()`, `mt_rand()`, or `array_rand()`

Sonar flags these as a "pseudorandom number generator in a security-sensitive
context" hotspot, even in seeders/tests, because the analyzer can't tell
intent from call site. Always use `random_int()` instead — it's a drop-in
replacement for `rand($min, $max)` / `mt_rand($min, $max)`.

```php
// Bad
rand(1, 5);
mt_rand(0, 1);
array_rand($items);

// Good
random_int(1, 5);
random_int(0, 1);
$items[array_keys($items)[random_int(0, count($items) - 1)]];
```

For picking N random elements from an Eloquent collection, prefer
`$collection->random($count)` (Laravel's own helper, not `array_rand`).

## Every validated string/file field needs a `max:`

Sonar's "content length limit" hotspot fires on `Request::validate()` /
`FormRequest::rules()` arrays that contain an unbounded `string`/`nullable`
field (no `max:`) — not just file uploads. When adding or editing a
validation rule for a text field (`nullable|string`, `required|string`,
textarea content, notes, free-text columns), always add a sensible `max:`
(e.g. `max:255` for a name/title, `max:2000`–`max:10000` for a notes/body
field). File uploads need `max:` in KB as usual.

## Group alternation inside regex character classes/subpatterns

Sonar's "group parts of the regex together" reliability bug fires when
operator precedence in a regex is ambiguous (e.g. bare `|` in a pattern
that also uses anchors/quantifiers, where intent is unclear without explicit
grouping). Wrap alternatives in a non-capturing group: `(?:foo|bar)` rather
than a bare `foo|bar` mixed with other pattern parts.

## Every `<label>`-able input needs an explicit id + `for`

Sonar's `Web:InputWithoutLabelCheck` fires on any Blade-rendered `<input>`,
`<select>`, or `<textarea>` that isn't associated with a `<label for="...">`
(wrapping the control in the label also counts, but an explicit `id`/`for`
pair is the safest and most consistent with this codebase's existing forms).
When adding a form field, always give it an `id` and pair it with
`<label for="that-id">`.

## Every `<th>` needs an `id` or `scope`

Sonar's table-header accessibility rule fires on any `<th>` without an `id`
or `scope="col"`/`scope="row"`. When writing or editing a `<table>` in Blade,
add `scope="col"` to header cells (`scope="row"` for row-header `<th>`s in
the body).

## External `<script src="https://...">` needs Subresource Integrity

When adding a `<script>` tag pointing at a third-party CDN, include
`integrity="sha384-..."` and `crossorigin="anonymous"`. Get the correct hash
from the CDN's own integrity snippet (jsDelivr/cdnjs provide one) — never
fabricate a hash.

## No hardcoded IP addresses

Sonar flags literal IP addresses in code/scripts as a hotspot (hostnames are
reviewable, bare IPs bypass DNS-based controls and are hard to rotate). Put
IPs behind a config value, env var, or hostname instead of hardcoding them in
scripts or source.

## CI/deploy: avoid running lifecycle scripts from untrusted sources

Sonar flags `npm ci`/`composer install` steps that don't disable arbitrary
package lifecycle scripts. Only add `--ignore-scripts` after confirming no
dependency in the lockfile actually needs a postinstall/build script to
function (check first — blindly adding it can silently break the build).
