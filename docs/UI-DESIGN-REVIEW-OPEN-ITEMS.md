# UI Design Review — Open Items

Distilled from a 90-file screenshot+notes design review dated 2026-09-11
(`docs/design-review-2026-09-11/`, now deleted). Most of that review's
findings were either resolved by subsequent work or never independently
re-verified; these are the items confirmed, on 2026-11-09, to still match
the current code.

## Admin → Federations (`GET /admin/federations`, route `federations.index`)

The same federations are listed twice on one page: an editable table
(Acronyme / Nom complet / Visibilité / Levels count-link / delete), then a
second "Certification Levels" section below with its own accordion, one
entry per federation. Editing a federation's levels means finding it twice.

Suggested restructure: merge into one list — each row expands in place to
reveal its certification levels; drop the separate "Certification Levels"
section entirely. Read-only row by default (Acronyme · Nom complet ·
Visibilité pill · "N niveaux" expand-link · edit/delete menu), per-row save
instead of a single "Save all federations", and the Visibilité values
(currently a FR/EN mix) translated.

Effort: M — controller already eager-loads levels; this is a view refactor
of `federations/index.blade.php` + its levels partial.

## Admin → Roles & Permissions (`GET /admin/roles`, route `roles.index`)

The permission matrix (~26 permissions × 9 role columns) overflows
horizontally — the rightmost role column clips at common viewport widths.
Permissions are a flat alphabetical list with no grouping, shown as raw
Spatie permission strings (English) in an otherwise-FR admin screen, no
per-row save. A second "Attribution des rôles" table (which members hold
which role) is unrelated in layout and truncates long member lists with no
way to expand.

Suggested restructure: group permissions into labelled, collapsible
sections matching the admin's own IA, with human-readable labels/tooltips
and the raw key kept as small muted text; freeze the permission-name column
and let role columns scroll within the table (or pivot to a one-role-at-a-time
editor); per-role save instead of one global save; move the attribution
table to its own section/tab with a working "view all" link.

Effort: M–L — sectioning/labels/tooltips/frozen-column is M; a per-role
editor is L but resolves the overflow and the 234-checkbox overwhelm at
once.

## Needs re-check (not conclusively verified either way)

Admin → Library (`/admin/library`) received real follow-up work since the
original review (folder create/rename/delete, drop zone, thumbnail fixes —
see commits `bf3ba0e`, `330f502`, `953b106`), but two specific complaints
from the original review weren't confirmed fixed or still-open: the
"1908 files but only 1 shown" global-count-vs-folder-view framing, and an
upload panel that's always expanded rather than collapsed by default.
Worth a direct look before assuming either is resolved.
