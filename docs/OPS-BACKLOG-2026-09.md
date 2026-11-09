# Ops & follow-up backlog — from the 2026-09-07 infra session

Everything the assistant surfaced or fixed that still needs a human. Roughly
priority order within each section. Session:
https://claude.ai/code/session_01D1ofaECGbqXJqNUU7M2QTt

---

## 0. Merge queue (open PRs)

Merge order doesn't matter much except #19 should land before you do the Redis
split (§2.3). All are green on lint/test/build; the only red check is the
Copilot `github-advanced-security` "model not supported" — ignore it.

- [x] **#18** — ✅ DONE (2026-11-09) — `.env.example`: document AI/LLM + `REDIS_DB` vars (docs only). Confirmed: `GROQ_API_KEY`/`CLOUDFLARE_*`/`REDIS_DB` all present in `.env.example` (commit `b8dbe73`).
- [x] **#19** — ✅ DONE (2026-11-09) — harden `ProcessTranslations` / `SendEquipmentReminders` queue jobs (commit `e1d94ee`).
- [x] **#20** — ✅ DONE (2026-11-09) — hide cancelled events from splash / `home2` (commits `276f0b0`, `9426f47`; see B3 below for the rest, also done).
- [x] **#21** — ✅ DONE (2026-11-09) — correct stale deployment/queue/backup docs (commits `234199d`, `4196186` at the time; note some of these counts have drifted again since and were re-fixed in a separate docs/ pass this session).
- [x] **#22** — ✅ DONE (2026-11-09) — avatar upload no longer dead-ends on a 419 (commit `ac64b76`).
- [ ] After merging: `ssh prod.clubcep.eu /opt/deploy/auto-deploy.sh` (or wait for 01:00), then verify.
- [ ] Deploy to **staging** too — its auto-deploy is suspended; it's currently
      on `main` again after this session but will drift. Re-enable the staging
      line in `/opt/deploy/auto-deploy.sh` or deploy it manually.

---

## 1. Prod scheduler (down since ~Aug 26 — no weekly backup since Aug 9)

- [ ] Add prod's own `schedule:run` cron (staging has one; prod has none):
      ```
      ssh prod.clubcep.eu '( crontab -u clubcep -l; echo "* * * * * cd /opt/deploy/apps/divingclub-prod && /usr/bin/php8.3 artisan schedule:run >> /dev/null 2>&1 && curl -fsS -m10 \"https://kuma.ecb.pm/api/push/irKO89KDzsVM6j5NOQBo?status=up&msg=OK\" >/dev/null 2>&1" ) | crontab -u clubcep -'
      ```
      (the trailing curl feeds Kuma monitor #8 — needs §4.1 first, harmless until then)
- [ ] Backup-freshness cron (feeds Kuma monitor #9):
      ```
      ssh prod.clubcep.eu '( crontab -u clubcep -l; echo "30 6 * * * f=\$(find /mnt/data/prod/backup/DivingClub -name \"*.zip\" -mtime -8 | head -1); curl -fsS -m10 \"https://kuma.ecb.pm/api/push/AYDHMAj1nxdNfQUB9zxA?status=\$([ -n \"\$f\" ] && echo up || echo down)&msg=\$([ -n \"\$f\" ] && echo fresh || echo stale)\" >/dev/null 2>&1" ) | crontab -u clubcep -'
      ```
- [ ] Once running, confirm `schedule_heartbeats` on prod advances and the next
      Sunday 03:00 `weekly-backup` fires.

---

## 2. Prod `.env` / server config

### 2.1 Session (fixes the avatar / logout 419s — see §3 B0)
- [ ] `SESSION_DOMAIN=prod.clubcep.eu` (currently `.clubcep.eu` — a super-domain
      cookie shared with test/www/auth; tracking-prevention drops it). Prod's
      session does **not** need to be readable by `auth.clubcep.eu` (verify:
      `EuLoginController` / `SocialAuthController` only redirect+bounce).
- [ ] `SESSION_SECURE_COOKIE=true` (currently `session.secure` resolves to `null`)
- [ ] `sudo -u clubcep /usr/bin/php8.3 artisan optimize:clear`; affected users
      re-login once.

### 2.2 GROQ key on prod
- [ ] Prod has `CLOUDFLARE_ACCOUNT_ID` + `CLOUDFLARE_API_TOKEN` but **not** `GROQ_API_KEY`:
      ```
      ssh prod.clubcep.eu 'grep ^GROQ_API_KEY= /opt/deploy/apps/divingclub/.env >> /opt/deploy/apps/divingclub-prod/.env && sudo -u clubcep /usr/bin/php8.3 /opt/deploy/apps/divingclub-prod/artisan optimize:clear && sudo -u clubcep /usr/bin/php8.3 /opt/deploy/apps/divingclub-prod/artisan horizon:terminate'
      ```
- [ ] (optional) GitHub Actions secrets — no workflow consumes the LLM keys yet;
      only add `GROQ_API_KEY` / `CLOUDFLARE_*` if you build one that needs them.

### 2.3 Shared Redis between staging & prod  ← do after #19 is deployed
Staging and prod share one Redis DB with no `REDIS_DB`/prefix separation → their
Horizon queues collide, so jobs run in the wrong environment (this is what filled
prod `failed_jobs` with ~335 rows and keeps `/health` "degraded").
- [ ] ```
      ssh prod.clubcep.eu '
        echo REDIS_DB=0 >> /opt/deploy/apps/divingclub-prod/.env
        echo REDIS_DB=1 >> /opt/deploy/apps/divingclub/.env
        for a in divingclub divingclub-prod; do sudo -u clubcep /usr/bin/php8.3 /opt/deploy/apps/$a/artisan optimize:clear; done
        supervisorctl restart horizon horizon-prod
        sudo -u clubcep /usr/bin/php8.3 /opt/deploy/apps/divingclub-prod/artisan queue:flush'
      ```
- [ ] Confirm prod `/health` returns `healthy` (200) afterwards and Kuma
      "Health PROD CEP" goes green.

### 2.4 PHP 8.5 CLI missing extensions (Step 7 — deferred, your call)
`php` on the box = 8.5 and lacks `mbstring`, `bcmath`, `intl`, `opcache`
(all present on `php8.3`; FPM is 8.3). Pick one:
- [ ] **A (recommended)** — repoint `update-alternatives --set php /usr/bin/php8.3`
      and update the `clubcep` crontab + `/etc/supervisor/conf.d/horizon*.conf`
      to `/usr/bin/php8.3`. Web and CLI then on the same version. Restart Horizon.
- [ ] **B** — `apt-get install -y php8.5-mbstring php8.5-bcmath php8.5-intl php8.5-opcache`
      and restart Horizon. Keeps the web/CLI version split.

### 2.5 Housekeeping
- [ ] Remove the stale `/opt/deploy/apps/divingclub-prod/php.ini` (Wasmer
      leftover — `upload_max_filesize=10M` — **inert** on Hetzner FPM, only
      misleads). Wasmer is fully decommissioned as a deployment target — the
      repo's own copy of this file (and `deploy-wasmer.sh`) has been deleted,
      so just remove the server-side leftover too, nothing to rename or keep.
- [ ] Drop the superseded staging stash (5 regression files + already-merged work):
      `ssh test.clubcep.eu 'sudo -u clubcep git -C /opt/deploy/apps/divingclub stash drop stash@{0}'`
      (`stash@{1..4}` are your older ones — leave them).
- [ ] Remove the orphan `/var/spool/cron/crontabs/deploy` (user `deploy` no longer exists).
- [ ] `production.WARNING: License check failed: invalid or expired license key` logs
      every ~15 min on prod — renew the self-hosted license key or disable the check
      for this install.
- [ ] `Cloudflare AI error {"status":429 … used up your daily free allocation of
      10,000 neurons}` — translation falls back to DeepL; consider a paid CF plan
      or throttling `ProcessTranslations`.

---

## 3. Bugs found while screenshotting every screen (need code)

- [ ] **B0** — avatar/logout 419s: PR #22 (code) + §2.1 (`.env`).
- [x] **B1** — ✅ DONE (2026-11-09) — `GET /admin/medical-certificates` → **HTTP 500**:
      `ZipArchive::close(): Read error: Is a directory` at
      `MedicalExportController.php:164`. The ZIP builder feeds a directory to
      `addFile()` (a member with no cert file, or a path that resolves to a
      dir). Guard `is_file()` before `addFile()`. **Federation-critical.**
      Confirmed: `is_file()` guards now present at `MedicalExportController.php:121,175`.
- [ ] **B2** — `/storage/event-photos/**/*.jpg` → **403** (dozens): files/dirs
      under `storage/app/public/event-photos/` aren't world-readable → broken
      images on the splash, gallery, event pages, home4 "recent articles".
      `ssh prod.clubcep.eu 'chmod -R a+rX /opt/deploy/apps/divingclub-prod/storage/app/public/event-photos'`
      and fix the upload path's umask/perms in code (`Storage::disk('public')->put`
      then `chmod 0644`).
- [x] **B3** — ✅ DONE (2026-11-09) — cancelled events still show in `/availability` and the
      `/admin/dashboard` "Prochains événements" widget (PR #20 only fixed the two
      public landing methods). Add `->where('status', '!=', 'cancelled')` there
      too — and to `HomeController::index4()` `$nextEvents` (line ~187).
      Confirmed: `HomeController.php:81,219` now filter `status != cancelled`, and
      `index4()`'s `$nextEvents` (line 250) uses a `notCancelled()` scope.
- [x] **B4** — ✅ DONE (2026-11-09) — mobile pagination renders literal **`pagination.previous` /
      `pagination.next`** — missing keys in `lang/*/pagination.php` (publish
      Laravel's pagination lang file, or add the keys). Confirmed: `lang/en/pagination.php`
      and `lang/fr/pagination.php` both exist.
- [x] **B5** — ✅ DONE (2026-11-09) — `/admin/members` "Show 30" selector but page size is 25
      (`x-per-page` value ignored by the controller's `->paginate()`). Confirmed: a shared
      `PaginatesFromRequest::perPage()` trait is now used in `Admin\MemberController`.
- [x] **B6** — ✅ DONE (2026-11-09) — `/admin/dashboard` "TOTAL DES MEMBRES" and "NOUVEAUX CETTE ANNÉE"
      both show 199 — the "new this year" count isn't filtered by join/adhesion year.
      Confirmed: `DashboardController.php:43` now computes it as
      `MemberDetail::where('adhesion_year', $season)->count()`, with a comment
      explicitly citing this bug as the reason.
- [ ] **B7** — `/home2` article body hot-links `upload.wikimedia.org` images →
      CSP-blocked → broken images. Fix the article content to use local assets.
      Still open (2026-11-09) — the 3 hardcoded `upload.wikimedia.org` URLs are
      still in `resources/views/home2.blade.php:175,179,183`.
- [x] **B8** — ✅ DONE (2026-11-09) — `/home4` "Articles récents" surfaces `SystemContent` shells
      ("Dues — footer note", "Bienvenue au CEP"). Exclude system slugs from the feed.
      Confirmed: `HomeController.php:251` now calls `Article::...->excludingSystem()->...`.

---

## 3b. Medical / personal document storage (from the storage review)

Storage, naming and access control are documented in `devdocs/medical.md` +
`devdocs/documents.md`. Access is sound (private disk, no `public/` symlink,
`auth` + owner/bureau check, signed-URL-only `serve` route). Follow-ups:

- [ ] **`is_current` never demoted** — `ProfileDocumentController::upload()`
      creates each new medical doc with `is_current = true` but never sets the
      previous one to `false` / `superseded_by`. Multiple "current" medical docs
      accumulate; compliance + export pick an arbitrary `->first()`. Still open
      (2026-11-09) — confirmed `ProfileDocumentController.php:62` still sets
      `is_current => true` with no corresponding demotion.
- [x] **B1 (also §3)** — ✅ DONE (2026-11-09) — `Admin\MedicalExportController::downloadCertificates`
      500s: `glob(private/medical/*)` returns the legacy per-member sub-dirs and
      `ZipArchive::addFile()` on a directory throws. Add `is_file()`. Same fix as
      B1 above, confirmed at `MedicalExportController.php:121,175`.
- [ ] **Health data unencrypted at rest**, world-readable at the FS level
      (mode ~0664/0775) on a shared box. Not web-reachable, but any local
      process can read it. Consider `chmod 0640` + dedicated group, or
      app-level encryption. Still open (2026-11-09) — no app-level encryption
      found in `Document`/`ProfileDocumentController`; FS permissions are a
      server-side state this review can't check.
- [x] **GDPR erasure gap** — ✅ DONE (2026-11-09) — `confirmErasure()` only deletes files that have a
      `documents` row; legacy certs in `storage/app/private/medical/` with no
      row survive an erasure request. Confirmed: `GdprController.php:95` now calls
      `deleteLegacyMedicalFiles()`, explicitly added to cover this exact gap.
- [x] Deterministic filenames (`Str::slug(name type date)`) silently overwrite a
      previous upload for the same person/type/date. ✅ DONE (2026-11-09) — a
      suffix-loop guard now exists in `ProfileDocumentController.php` (~lines 47-50).
- [ ] Minor: medical-upload notification is `Mail::raw` to bureau with member
      name + cert type + date in plaintext email.

## 4. Monitoring (Uptime Kuma on kuma.ecb.pm)

Done this session: HTTP monitors **PROD CEP**, **www CEP**, **AUTH CEP** (all
green); **Health PROD CEP** is red until §2.3. Push monitors #8 *PROD scheduler*
and #9 *PROD backup freshness* were created but are **pending** — the push
endpoint is behind your SSO.

### 4.1 Exempt the push endpoint from SSO
- [ ] Edit `/opt/caddy/sites/kuma.caddy` on ecb.pm to:
      ```
      kuma.ecb.pm {
          @needsauth not path /api/push/* /api/badge/*
          forward_auth @needsauth auth:8080 { uri /verify
              copy_headers { X-Auth-User } }
          reverse_proxy kuma:3001
      }
      ```
      then `docker exec caddy caddy reload --config /etc/caddy/Caddyfile`
- [ ] Then §1's two curl lines start feeding monitors #8/#9.

### 4.2 Nice to add later
- [ ] A monitor for `test.clubcep.eu` staging Horizon, and disk-usage on the box.

---

## 5. Test suite

- [x] **e2e credentials**: ✅ DONE (2026-11-09) — `tests/e2e/*.py` hardcode `eddy.collart@gmail.com` and
      three *different wrong* passwords. Parametrise via env
      (`E2E_BASE` / `E2E_USER` / `E2E_PASS`). Confirmed: `tests/e2e/_e2e_config.py`
      now reads all three from the environment, `E2E_PASS` with no default.
- [x] **e2e throttle**: ✅ DONE (2026-11-09) — `test_journeys.py` + `test_adversarial.py` can't run
      back-to-back (or `pytest tests/e2e/` in one shot) — the shared login
      trips the rate limiter and every `auth_page` test then lands on `/login`.
      Add a 429 backoff to the `login()` helpers, or make the fixtures
      session-scoped and shared. Confirmed: `_e2e_config.py` now has a shared
      `_LOGIN_BACKOFF` schedule and `submit_login()` retry helper for exactly this.
- [x] **stale test**: ✅ DONE (2026-11-09) — `test_journeys.py::test_nationality_grouped_dropdown` looks
      for `<select name=nationality>` with `<optgroup>`s — PR #16 replaced that
      with the `<x-country-select>` datalist. It currently passes vacuously.
      Confirmed: that test is gone, replaced by `test_nationality_combobox`, which
      explicitly tests the current `input[list]`/`<datalist>` markup.
- [x] **doc counts**: ✅ DONE (2026-11-09) — `docs/TESTING-GUIDE.md` ("35 + 49 tests, 233 PHPUnit,
      updated April 8") is stale — PHPUnit is now ~442, e2e is 35 + 51 + 38
      (`test_journeys.py` isn't listed at all). Confirmed: `docs/TESTING-GUIDE.md`
      was corrected in a separate docs/ pass this session (187 test files /
      ~1,120 test methods, 4 mail providers).
- [ ] **new crawlers** (cheap, high value) over every route:
      - no visible text matching `/pagination\.|^[a-z_]+\.[a-z_.]+$/` (catches B4)
      - every `<img>` has `naturalWidth > 0` (catches B2 / B7)
      - zero `console.error` per page
- [ ] **journey coverage** — only ~13 of the 63 documented journeys are
      automated. Priority gaps: J6 cancel registration · J8/J9 vote cast+results ·
      J10 verify medical cert · J13 season→generate events · J14 payment +
      reconciliation CSV · J17 annual election lifecycle · J24 club identity
      round-trip · J27/J28 partnership + federated event (API) · J32 dive-group
      planner rule violation · J34 minor/guardian consent · J35 event photo +
      GDPR · J39 dues calculator math · J43 dive site CRUD · J46 annual report ·
      J49 password reset.

---

## 6. UX / design — prioritised (full detail in the session transcript)

### P1 — broad or club-facing
- [x] **Finish the French locale** — ✅ DONE (2026-11-09) for the strings cited here —
      ~⅓ of strings fall back to English on FR:
      nav (`About`, `Resources`, `My Account`), admin sidebar (`Minors`, `Trials`,
      `Partners`, `Roles`, `Backups`), table headers (`VISIBILITY`, `DÉFINIR`),
      calendar + instructor-planning legends, profile (`Status Set (base
      category)` + helptext, `Membership Status`), dashboard (`BUREAU WORKLIST`,
      `Medical certificates to verify`, `SCHEDULED TASKS`, `EMAIL SENDING QUOTA`).
      Add a CI lint for untranslated visible strings. Confirmed: every string
      named above now has a French entry in `lang/fr.json` (e.g. "About" →
      "À propos", "Email Sending Quota" → "Quota d'envoi d'emails"). Not
      re-verified: the "~⅓ of strings" estimate as a whole, or whether the CI
      lint was ever added — only the specifically-named strings were checked.
- [ ] **Fix the public splash** — hero image (B2), the four stat counters render
      **0** until you scroll (render the real value, animate as enhancement),
      empty coloured bands when there's no media, FR text vs EN buttons.
- [ ] **Responsive data tables** — `/admin/members` (and others) overflow 1440px
      and clip on mobile with no scrollbar. Add `overflow-x:auto` wrappers and a
      stacked-card layout under ~768px.
- [ ] **De-clutter `/admin/members`** — drop the redundant `Voir` (rows are
      clickable) and one of the two impersonate controls (🔑 icon + "Se faire
      passer pour"); one status column not two; edit-on-click for role; fix B5.

### P2 — admin efficiency & consistency
- [ ] **Consolidate `/admin/dashboard`** — KPI cards appear in 4 different
      styles/locations down a ~3200px column; birthdays shown twice; right half
      of the page empty. One KPI grid, one birthdays block, two columns. Fix B6.
- [ ] **Per-row "Enregistrer" → silent AJAX auto-save** for Settings →
      Fédérations, dive-group-rules, etc. (the pattern you standardised in
      `trip-settlement/manage`).
- [ ] **Truncation** — federation names, emails, event titles, role/status
      values are all cut with `…`. Widen or wrap.
- [ ] **Headings & icons** — pick emoji-or-not and apply consistently; add
      breadcrumbs to admin pages missing them; rename one of "Audit" /
      "Journal d'audit" (two different things, same sidebar).
- [ ] **Calendar** — translate the type legend; verify pill colours match it
      ("Entraînements collectifs" rendered Theory-purple, not Training-green).

### P3 — polish
- [ ] Profile: style the raw `<input type=file>`; separate/relocate the
      bureau-only fields from the member's own edit form; legend/tooltip on the
      cotisation-year squares (they show "27 23 24 25 26 27" — looks buggy).
- [ ] "Our Members" stats: note why "93 active" here vs "199 total" on the dashboard.
- [ ] Cookie banner is `position:fixed` and overlaps chart content — reserve space.
- [ ] Instructor Planning: the 3 "Jerome/Jerome/Jérôme" pills are
      indistinguishable — show the disambiguation initial (J/T/B) or surname; the
      top picker duplicates the bottom name legends.
- [ ] Top-right control cluster (dark-mode · A- A+ · lang · name · avatar ·
      unlabelled orange quota bar) is busy — collapse into one menu.
