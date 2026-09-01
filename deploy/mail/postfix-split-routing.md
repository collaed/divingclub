# Postfix Split Routing — clubcep.eu (migration coexistence)

This document describes the Postfix configuration that lets the new Laravel
pipeline and the legacy `mailercodeV5.php` scripts run side by side during
migration. It is a configuration artifact and runbook; it is **not** applied to
production automatically. Apply it manually on the VPS (`204.168.168.60`) as
root after review.

Companion documents:

- `deploy/mail/setup-postfix-cutover.sh` — the initial MX cutover (routes all
  `clubcep.eu` mail to the Laravel pipe).
- `devdocs/email-cases.md` §2.2 — the SAS alias cases and which side owns each.

---

## 1. Routing Goal

| Recipient pattern | Destination | Rationale |
|-------------------|-------------|-----------|
| `members@`, `members.b@`, `members.m@`, `members.s{id}@`, `members.pn{n}@`, `bureau@`, `instructors@`, `event-{id}@`, `cep+*@`, `year=*` | **Laravel** (`laravel-pipe`) | New system handles all group routing and event append. |
| `sas+conv.{token}@` | **Laravel** (`laravel-pipe`) | New conversation reply channel (case 8). |
| `sas.{nickname}@` | **Legacy host** | Legacy static vanity aliases (case 9) not yet migrated. |
| `sas+{name}-at-{domain}@` | **Legacy host** | Legacy on-the-fly proxies (case 10) not yet migrated. |
| anything else | **Laravel** (`laravel-pipe`) | Unknown aliases triaged by the Bureau fallback. |

The critical distinction is within the `sas` namespace: `sas+conv.*` goes to
Laravel, while bare `sas.*` and other `sas+*` proxies go to the legacy host.
Because Postfix `transport_maps` keys match the full local part (with
`recipient_delimiter = +`, the part after `+` is the "extension"), we use a
`pcre` transport map to distinguish these by regular expression.

---

## 2. Configuration

### 2.1 Legacy host relay transport

Define a relay transport that hands a message to the legacy server over SMTP.
Replace `142.4.216.50` with the legacy host's current address if it changed.

Add to `/etc/postfix/master.cf` (if not already present) — the `laravel-pipe`
transport already exists from the cutover script. Add a `legacy-relay`:

```
# /etc/postfix/master.cf
legacy-relay unix -       -       n       -       -       smtp
    -o smtp_fallback_relay=
```

Then map the transport nexthop in `main.cf`:

```
postconf -e "legacy_relay_host = [142.4.216.50]:25"
```

### 2.2 PCRE transport map (the split)

Create `/etc/postfix/transport_split.pcre`:

```
# sas+conv.{token}@ → Laravel (new conversation channel). Must come BEFORE the
# generic sas rules so it wins.
/^sas\+conv\.[^@]+@(clubcep\.eu|test\.clubcep\.eu)$/    laravel-pipe:

# Legacy static vanity aliases: sas.{nickname}@ → legacy host.
/^sas\.[^@]+@(clubcep\.eu|test\.clubcep\.eu)$/          legacy-relay:[142.4.216.50]:25

# Legacy on-the-fly proxies: sas+{name}-at-{domain}@ → legacy host.
/^sas\+[^@]*-at-[^@]*@(clubcep\.eu|test\.clubcep\.eu)$/ legacy-relay:[142.4.216.50]:25

# Everything else for the club domains → Laravel.
/@(clubcep\.eu|test\.clubcep\.eu)$/                     laravel-pipe:
```

Order matters: PCRE maps are evaluated top to bottom, first match wins. The
`sas+conv.` rule must precede the generic `sas+...-at-...` rule.

Wire it up (the PCRE map takes precedence over the hash map from the cutover):

```
postconf -e "transport_maps = pcre:/etc/postfix/transport_split.pcre, hash:/etc/postfix/transport"
systemctl reload postfix
```

`pcre` requires the `postfix-pcre` package:

```
apt-get install -y postfix-pcre
```

---

## 3. Verification

Query the map directly (no mail sent):

```
postmap -q "sas+conv.abc123@clubcep.eu"        pcre:/etc/postfix/transport_split.pcre   # → laravel-pipe:
postmap -q "sas.fanfan@clubcep.eu"             pcre:/etc/postfix/transport_split.pcre   # → legacy-relay:...
postmap -q "sas+macron-at-elysee.fr@clubcep.eu" pcre:/etc/postfix/transport_split.pcre  # → legacy-relay:...
postmap -q "bureau@clubcep.eu"                 pcre:/etc/postfix/transport_split.pcre   # → laravel-pipe:
postmap -q "members.s42@clubcep.eu"            pcre:/etc/postfix/transport_split.pcre   # → laravel-pipe:
```

Laravel-side, confirm each new alias class resolves and logs by dropping a
fixture into the inbound Maildir (or piping to `mail:inbound`) and checking
`email_log`:

```
echo "Test body" | php artisan mail:inbound --to=members.s42@clubcep.eu --from=<known-instructor>
php artisan tinker --execute="App\\Models\\EmailLog::latest()->first()->only(['to_email','event_id','status']);"
```

The legacy `sas.*` and `sas+...-at-...` classes should **not** appear in
`email_log` — Postfix routes them away before Laravel sees them.

---

## 4. Cutover Order

1. Deploy the Laravel code (migrations run: `mail_aliases`, `mail_conversations`).
2. Set `.env` on the VPS:
   ```
   CLUB_MAIL_ADDRESS=cep@clubcep.eu
   CLUB_NOREPLY_ADDRESS=no-reply@clubcep.eu
   CLUB_LOG_MAILBOX=mail-log@clubcep.eu
   INBOUND_MAIL_ENABLED=true
   INBOUND_MAIL_MODE=maildir
   ```
   then `php artisan config:clear`.
3. Install `postfix-pcre`, add the PCRE transport map, reload Postfix.
4. Verify with `postmap -q` for each class in §3.
5. Send a live test to `bureau@`, `event-{id}@`, and `sas+conv.{token}@` and
   confirm forwarding + `email_log` rows.
6. Send a live test to a legacy `sas.{nickname}@` and confirm it is delivered by
   the legacy host (check the legacy `mailerlog.txt`).

---

## 5. Rollback

- Remove the PCRE map from `transport_maps` (leaving only the hash map) and
  reload Postfix. All `clubcep.eu` mail then flows to the Laravel pipe again
  (the cutover default), and the legacy `sas` handling is bypassed.
- If the entire new pipeline must be disabled, set `INBOUND_MAIL_ENABLED=false`
  and revert the MX record per `setup-postfix-cutover.sh` §ROLLBACK.

---

## 6. Retiring the Legacy SAS Cases

Once every legacy `sas.{nickname}` alias has been migrated to a `type=sas_static`
row in `mail_aliases` (and the corresponding resolution logic is added to
`MailAliasService`), delete the two `legacy-relay` lines from
`transport_split.pcre` so all `sas` traffic flows to Laravel, then decommission
the legacy host.
