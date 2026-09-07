# Ops & follow-up backlog — from the 2026-09-07 infra session

Everything the assistant surfaced or fixed that still needs a human. Roughly
priority order within each section. Session:
https://claude.ai/code/session_01D1ofaECGbqXJqNUU7M2QTt

---

## 0. Merge queue (open PRs)

Merge order doesn't matter much except #19 should land before you do the Redis
split (§2.3). All are green on lint/test/build; the only red check is the
Copilot `github-advanced-security` "model not supported" — ignore it.

- [ ] **#18** — `.env.example`: document AI/LLM + `REDIS_DB` vars (docs only)
- [ ] **#19** — harden `ProcessTranslations` / `SendEquipmentReminders` queue jobs
- [ ] **#20** — hide cancelled events from splash / `home2` (see B3 for the rest)
- [ ] **#21** — correct stale deployment/queue/backup docs
- [ ] **#22** — avatar upload no longer dead-ends on a 419
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
      misleads). The repo copy is legit Wasmer config; consider renaming it
      `php.wasmer.ini` and pointing `deploy-wasmer.sh` at it.
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
- [ ] **B1** — `GET /admin/medical-certificates` → **HTTP 500**:
      `ZipArchive::close(): Read error: Is a directory` at
      `MedicalExportController.php:164`. The ZIP builder feeds a directory to
      `addFile()` (a member with no cert file, or a path that resolves to a
      dir). Guard `is_file()` before `addFile()`. **Federation-critical.**
- [ ] **B2** — `/storage/event-photos/**/*.jpg` → **403** (dozens): files/dirs
      under `storage/app/public/event-photos/` aren't world-readable → broken
      images on the splash, gallery, event pages, home4 "recent articles".
      `ssh prod.clubcep.eu 'chmod -R a+rX /opt/deploy/apps/divingclub-prod/storage/app/public/event-photos'`
      and fix the upload path's umask/perms in code (`Storage::disk('public')->put`
      then `chmod 0644`).
- [ ] **B3** — cancelled events still show in `/availability` and the
      `/admin/dashboard` "Prochains événements" widget (PR #20 only fixed the two
      public landing methods). Add `->where('status', '!=', 'cancelled')` there
      too — and to `HomeController::index4()` `$nextEvents` (line ~187).
- [ ] **B4** — mobile pagination renders literal **`pagination.previous` /
      `pagination.next`** — missing keys in `lang/*/pagination.php` (publish
      Laravel's pagination lang file, or add the keys).
- [ ] **B5** — `/admin/members` "Show 30" selector but page size is 25
      (`x-per-page` value ignored by the controller's `->paginate()`).
- [ ] **B6** — `/admin/dashboard` "TOTAL DES MEMBRES" and "NOUVEAUX CETTE ANNÉE"
      both show 199 — the "new this year" count isn't filtered by join/adhesion year.
- [ ] **B7** — `/home2` article body hot-links `upload.wikimedia.org` images →
      CSP-blocked → broken images. Fix the article content to use local assets.
- [ ] **B8** — `/home4` "Articles récents" surfaces `SystemContent` shells
      ("Dues — footer note", "Bienvenue au CEP"). Exclude system slugs from the feed.

---

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

- [ ] **e2e credentials**: `tests/e2e/*.py` hardcode `eddy.collart@gmail.com` and
      three *different wrong* passwords. Parametrise via env
      (`E2E_BASE` / `E2E_USER` / `E2E_PASS`).
- [ ] **e2e throttle**: `test_journeys.py` + `test_adversarial.py` can't run
      back-to-back (or `pytest tests/e2e/` in one shot) — the shared login
      trips the rate limiter and every `auth_page` test then lands on `/login`.
      Add a 429 backoff to the `login()` helpers, or make the fixtures
      session-scoped and shared.
- [ ] **stale test**: `test_journeys.py::test_nationality_grouped_dropdown` looks
      for `<select name=nationality>` with `<optgroup>`s — PR #16 replaced that
      with the `<x-country-select>` datalist. It currently passes vacuously.
- [ ] **doc counts**: `docs/TESTING-GUIDE.md` ("35 + 49 tests, 233 PHPUnit,
      updated April 8") is stale — PHPUnit is now ~442, e2e is 35 + 51 + 38
      (`test_journeys.py` isn't listed at all).
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
- [ ] **Finish the French locale** — ~⅓ of strings fall back to English on FR:
      nav (`About`, `Resources`, `My Account`), admin sidebar (`Minors`, `Trials`,
      `Partners`, `Roles`, `Backups`), table headers (`VISIBILITY`, `DÉFINIR`),
      calendar + instructor-planning legends, profile (`Status Set (base
      category)` + helptext, `Membership Status`), dashboard (`BUREAU WORKLIST`,
      `Medical certificates to verify`, `SCHEDULED TASKS`, `EMAIL SENDING QUOTA`).
      Add a CI lint for untranslated visible strings.
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
