# Mail Routing Transition Plan — clubcep.eu

## Current State

### Legacy System (cPanel on shared hosting, PHP 5.6)

All inbound mail to `@clubcep.eu` is handled by the legacy hosting provider. Key aliases pipe through `mailerDA.php` (a PHP 5.6 script that resolves recipients from the Joomla database and forwards).

| Legacy Alias | Behavior | Recipients |
|---|---|---|
| `members@clubcep.eu` | Pipe to `mailerDA.php` + copy to `all-sas@clubcep.eu` | All active members |
| `members.b@clubcep.eu` | Pipe to `mailerDA.php` + copy to `all-sas@clubcep.eu` | Bureau members |
| `members.m@clubcep.eu` | Pipe to `mailerDA.php` + copy to `all-sas@clubcep.eu` | Moniteurs (instructors) |
| `sas.emmanuel@clubcep.eu` | Pipe to `mailerDA.php` + copy to `all-sas@clubcep.eu` | Vanity alias → Emmanuel's private email |
| `sas.etienne@clubcep.eu` | Same | Vanity alias → Etienne's private email |
| `sas.mariejo@clubcep.eu` | Same | Vanity alias → Marie-Jo's private email |
| `sas.pascale@clubcep.eu` | Same | Vanity alias → Pascale's private email |
| `mail1` through `mail10` | Forward to `mail@clubcep.eu` | Outbound sending aliases (load balancing) |
| `all-sas@clubcep.eu` | Mailbox | Club functional Gmail — receives CC of all forwarded mail, uses Gmail rules to route to bureau |
| `vote@clubcep.eu` | Mailbox | Vote notifications |

### New System (Laravel on Hetzner VPS)

`PollInboundMail` job polls via IMAP or Maildir. `MailAliasService` resolves aliases to recipient lists from the new database. Auth gate: only bureau/instructors can send.

| New Alias | Resolves To | Auth |
|---|---|---|
| `bureau` / `members.b` | Bureau members (detail.bureau_member = true) | Bureau only |
| `members` / `all` | All active members (verified email) | Bureau only |
| `instructors` / `moniteurs` / `members.m` | Active instructors | Bureau or instructor |
| `event-{id}` / `members.s{id}` | Confirmed registrations for event | Bureau, instructor, or participant |
| `members.pn1/pn2/pn3` | Training level enrollments | Bureau or instructor |
| `year={YYYY}` | Members who paid dues that year | Bureau only |

Plus-addressing supported: `cep+event.42@clubcep.eu`, `cep+bureau@clubcep.eu`.

---

## Gap Analysis

| Legacy | New System | Status |
|---|---|---|
| `members@` → all active | `members` / `all` | ✅ Built |
| `members.b@` → bureau | `bureau` / `members.b` | ✅ Built |
| `members.m@` → instructors | `instructors` / `members.m` | ✅ Built |
| `sas.emmanuel@` → ? | Not yet mapped | ⚠️ Need to add vanity alias support |
| `sas.etienne@` → ? | Not yet mapped | ⚠️ Same |
| `sas.mariejo@` → ? | Not yet mapped | ⚠️ Same |
| `sas.pascale@` → ? | Not yet mapped | ⚠️ Same |
| `mail1-10@` → outbound | Load-balanced SMTP in new system | ✅ Built (3 providers) |
| `all-sas@` → archive | EmailLog table | ✅ Built (all forwarded mail logged) |
| `vote@` → vote notifications | Vote system sends directly | ✅ Built |
| Event dynamic addresses | `event-{id}@` + `members.s{id}@` | ✅ Built |
| `mailerDA.php` auth gate | `PollInboundMail` sender check | ✅ Built |
| CC to `all-sas` on every forward | EmailLog stores all | ✅ Built (no separate CC needed) |

---

## Open Questions (for Eddy)

1. ~~**What are the `sas.*` aliases?**~~ **Resolved**: Vanity/functional aliases so bureau members don't expose personal addresses. All also CC to the club's Gmail functional mailbox (`all-sas@clubcep.eu`), which uses Gmail rules to forward to relevant bureau members.

2. **Is `mailerDA.php` still actively used?** Or has all mail already been migrated to the new system's `PollInboundMail`? The script lives at `/home/clubcepe/mailForward/mailerDA.php` (not in the backup — need FTP to retrieve).

3. **MX records**: Where do MX records for `clubcep.eu` currently point? The legacy shared host? If so, we need to route inbound mail to the VPS.

---

## Transition Plan

### Phase 1: Parallel Operation (no MX change)

1. Configure the legacy host to forward a copy of all aliased mail to a catch-all on the VPS (e.g. `inbound@test.clubcep.eu` or via IMAP polling).
2. Enable `PollInboundMail` in IMAP mode, pointing at the legacy host's IMAP server.
3. Verify: send test emails to `members.b@clubcep.eu` → confirm they arrive in both the legacy system AND the new system's EmailLog.
4. Map the `sas.*` aliases once their purpose is clarified.

### Phase 2: Shadow Mode (new system processes, legacy still delivers)

1. `PollInboundMail` processes and forwards all mail through the new system.
2. Legacy `mailerDA.php` continues to run in parallel (safety net).
3. Monitor for 2-4 weeks. Compare delivery logs.
4. Resolve any edge cases (bounces, encoding issues, attachments).

### Phase 3: Cutover (MX change)

1. Point MX records for `clubcep.eu` to the Hetzner VPS (or a relay that delivers to Maildir on the VPS).
2. Disable `mailerDA.php` on the legacy host.
3. Keep legacy host aliases as simple forwards to the VPS catch-all (fallback).
4. Remove IMAP polling config, switch to Maildir mode (faster, no polling delay).

### Phase 4: Cleanup

1. Remove legacy forwarding rules.
2. Retire `all-sas@clubcep.eu` mailbox (replaced by EmailLog).
3. Retire `mail1-10` outbound aliases (replaced by multi-provider SMTP in Laravel).
4. Document final alias map in admin guide.

---

## Technical Requirements for Cutover

### On the VPS (204.168.168.60)

- **MTA**: Install Postfix or Caddy's SMTP handler to accept inbound mail for `@clubcep.eu`.
- **Maildir delivery**: Configure to deliver to `/home/inbound/Maildir/new/`.
- **Alias routing**: Postfix `virtual_alias_maps` catches all `@clubcep.eu` and delivers to the single Maildir.
- **TLS**: Ensure valid cert for `clubcep.eu` on port 25/587.
- **SPF/DKIM/DMARC**: Already configured for outbound; verify inbound acceptance.

### DNS Changes

```
clubcep.eu.  MX  10  mail.clubcep.eu.
mail.clubcep.eu.  A  204.168.168.60
```

### Laravel Config

```env
INBOUND_MAIL_ENABLED=true
INBOUND_MAIL_MODE=maildir
INBOUND_MAILDIR=/home/inbound/Maildir
```

### Scheduled Task

```php
// routes/console.php — already exists
Schedule::job(new PollInboundMail)->everyMinute();
```

---

## Alias Mapping (Final State)

| Address | Resolves To | Auth Required |
|---|---|---|
| `members@clubcep.eu` | All active members | Bureau |
| `bureau@clubcep.eu` | Bureau members | Bureau |
| `members.b@clubcep.eu` | Bureau members (legacy compat) | Bureau |
| `instructors@clubcep.eu` | Active instructors | Bureau or instructor |
| `members.m@clubcep.eu` | Active instructors (legacy compat) | Bureau or instructor |
| `event-{id}@clubcep.eu` | Event participants | Bureau, instructor, or participant |
| `members.s{id}@clubcep.eu` | Event participants (legacy compat) | Bureau, instructor, or participant |
| `members.pn1@clubcep.eu` | N1 training group | Bureau or instructor |
| `members.pn2@clubcep.eu` | N2 training group | Bureau or instructor |
| `members.pn3@clubcep.eu` | N3 training group | Bureau or instructor |
| `cep+{tag}@clubcep.eu` | Plus-addressing (any of the above) | Per-alias |
| `sas.emmanuel@clubcep.eu` | Vanity alias → Emmanuel's private email + CC to club Gmail | Bureau |
| `sas.etienne@clubcep.eu` | Vanity alias → Etienne's private email + CC to club Gmail | Bureau |
| `sas.mariejo@clubcep.eu` | Vanity alias → Marie-Jo's private email + CC to club Gmail | Bureau |
| `sas.pascale@clubcep.eu` | Vanity alias → Pascale's private email + CC to club Gmail | Bureau |

---

## Risk Mitigation

- **No mail loss**: Phase 1-2 run in parallel. Both systems deliver.
- **Rollback**: If the new system fails, revert MX to legacy host (5-minute DNS TTL during cutover).
- **Attachment handling**: `PollInboundMail` already parses MIME multipart. Attachments are stripped from the forwarded body but the original is logged.
- **Encoding**: MIME header decoding (UTF-8, Base64, QP) already implemented in `PollInboundMail`.

## Operational Decisions (for the board)

### Archival Strategy

`EmailLog` stores full HTML body (truncated at 5000 chars). For a full payload archive including attachments, two options:
- **Option A (recommended)**: Keep `all-sas@gmail.com` as a BCC on all forwarded mail. Zero code change, preserves existing searchable Gmail archive.
- **Option B**: Store raw `.eml` files to disk and reference from `EmailLog`. Only needed if Gmail is retired.

### Outbound Reply-From (vanity address on replies)

When a bureau member receives a forwarded email and hits Reply, their personal address is exposed. This is the same limitation as the legacy system. Fix options:
- **Option A (recommended)**: Bureau members add `sas.{name}@clubcep.eu` as a "Send As" alias in their personal email client (Gmail: Settings → Accounts → Add another email). One-time 2-minute setup per person. Requires SMTP auth on the VPS (Postfix with SASL).
- **Option B**: Build a reply UI in the Laravel dashboard. Over-engineered for 4 bureau members.
