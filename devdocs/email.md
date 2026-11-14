## email.md — Email Pipeline

## Outbound (sending emails to members)

### Architecture

Four providers load-balanced via `MailBalancer`, picked by lowest usage-today among those still under their daily cap (ties broken in this order):
1. Resend (primary) — `RESEND_KEY`, 98/day
2. Resend (secondary) — `RESEND_KEY_SECONDARY`, a second Resend account/key, 98/day
3. Mailjet — via local Postfix/sendmail relay (`MAILJET_KEY`/`MAILJET_SECRET` used only for its stats API, not sending), 200/day (≈6000/month free tier)
4. Brevo — `BREVO_API_KEY`, 300/day

A provider is only picked if it has credentials configured (`MailBalancer::isConfigured()`); if every provider is unconfigured or over its cap, Mailjet is used anyway since it's a local relay needing no API key. Daily counts are tracked in `MailSendStat` (not cache), so `optimize:clear` on deploy never resets rotation.

`MailBalancer::configureForNext()` selects the next provider and configures Laravel's mailer dynamically before each send. Amazon SES is still registered as a mail transport (`config/mail.php`, `AWS_*` credentials) but is not part of the `MailBalancer` rotation — currently unused.

### Target Groups (6)

| Group | Resolves To |
|-------|-------------|
| All active | members with status in [actif, membre_de_droit, fonctionnaire] + verified email |
| Bureau | members with `detail.bureau_member = true` |
| Instructors | members with `detail.active_instructor = true` |
| Event participants | confirmed registrations for a specific event |
| Training level | members enrolled in N1/N2/N3 |
| By dues year | members with specific cotisation year |

### Templates

`email_templates` table: slug, subject, body (with `{{ variable }}` placeholders).
Bureau can create/edit/delete templates via admin UI.

### Send Flow

```
Admin composes → selects target group → preview → send
  → EmailController::send()
    → MailAliasService::resolve(group) → list of emails
    → For each recipient:
      → MailBalancer::configureForNext()
      → Mail::send() with template + variables
      → EmailLog::create(direction: outbound, status: sent/failed)
```

### Logging

All sent/received emails stored in `email_log`:
- direction (inbound/outbound), to_email, from_email, subject, body (truncated 5000 chars)
- status (sent, failed, forwarded, rejected, pending_review)
- event_id (if related to an event)

## Inbound (receiving emails to club aliases)

### Architecture

`PollInboundMail` job (runs every minute):
- Mode 1: **Maildir** — reads files from `/home/inbound/Maildir/new/`
- Mode 2: **IMAP** — polls a mailbox via IMAP (legacy host)

### Alias Resolution (MailAliasService)

| Alias Format | Resolves To | Auth Required |
|---|---|---|
| `members@clubcep.eu` | All active members | Bureau only |
| `bureau@clubcep.eu` / `members.b@` | Bureau members | Bureau only |
| `instructors@clubcep.eu` / `members.m@` | Active instructors | Bureau or instructor |
| `event-{id}@clubcep.eu` / `members.s{id}@` | Event participants | Bureau, instructor, or participant |
| `members.pn1/2/3@` | Training groups | Bureau or instructor |
| `cep+{tag}@clubcep.eu` | Plus-addressing (any of above) | Per-alias |
| `sas.{name}@clubcep.eu` | Vanity alias → bureau member's personal email + CC club Gmail | Bureau |

### Forwarding Rules (admin/settings → Technical → Email & Forwarding)

`App\Models\MailForwardingRule` (table `mail_forwarding_rules`, managed on
that admin page) lists addresses that aren't club-internal aliases — e.g.
the public contact address, `config('club.contact_email')` — where the
sender comes from the public, who aren't `users` rows and would otherwise
be rejected by the sender-authorization check below. If an enabled rule's
`from_address` matches the message's `to`, `PollInboundMail` forwards it
to the rule's `to_addresses` unconditionally, bypassing alias resolution
and the bureau/instructor sender check entirely, before any of the steps
below run.

### Processing Pipeline

```
PollInboundMail fetches message
  → Extract from, to, subject, body (handles MIME multipart, base64, QP)
  → (forwarding-rule check — see above — short-circuits here if a rule matches)
  → Check subject for (recipients: ...) directive override
  → MailAliasService::resolve(to) → get recipient list
  → Auth check: sender must be bureau or instructor (else → rejected, logged)
  → InboundMailFilter::filter(body, eventId, senderEmail):
    1. DOM-based HTML signature stripping (XPath + config anchors)
    2. Plain text signature dictionary (config/mail_signatures.php)
    3. Global device footers ("Sent from my iPhone"...)
    4. Standard delimiters ("-- \n", "Cordialement", "Best regards")
    5. Quoted reply removal ("On ... wrote:" + blockquotes)
    6. Corporate disclaimer stripping (EU Commission, confidentiality)
    7. Optional AI moderation (flags private/irrelevant content)
  → Forward cleaned body to all resolved recipients
  → Send confirmation to sender ("Your message was sent to N recipients")
  → Log to email_log
```

### Signature Stripping (config/mail_signatures.php)

Per-domain exact anchors to prevent false positive truncation:
```php
'tti-network.com' => ['text_anchor' => 'Keran CHAUSSARD'],
'ec.europa.eu' => ['text_anchor' => 'European Commission'],
'eib.org' => ['text_anchor' => 'European Investment Bank'],
```

### Simulation Mode

Subject contains `(recipients: simulate, bureau)` → sends back a preview of who would receive the email instead of actually forwarding.

## Newsletters

Separate from the email system. Rich HTML newsletters with themed templates:
- `newsletters` table: title, month, status (draft/approved/sent), slots (JSON)
- Approval workflow: `newsletter_approvals` table
- Themes: `bulles`, `abyss`, `coral`, `arctic` — named stencil-image sets, matched from `background_image` (`NewsletterController::buildEmailData()`), default `bulles`
- `background_image` is either a direct custom-image upload (`storage('newsletters')`) or one of the 4 preset theme names, matched via `str_contains()` in `NewsletterController::buildEmailData()` — no slicing/cropping and no AI image generation involved (`App\Services\NewsletterStencilSlicer` exists in the codebase but has zero callers — dead code)
- Rendered HTML is stored directly on the `newsletters.published_html` column, not as a file on disk
