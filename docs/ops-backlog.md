## Move MailBalancer to an OS-level shared component (deferred)

**Idea:** Staging and production run on the same Hetzner host but each has its
own database, so each tracks its own daily per-provider mail counts — even
though the real Resend/Brevo accounts (API keys) and the local Postfix relay
(`mailjet` provider) are actually shared between both apps. On 2026-09-14 a
~84-email staging test burned most of the day's real 100/day Resend quota
before production's own dispatch even started, and production's own counters
never saw it coming since it lived in a separate database.
- **Read the API's real return, not just a periodic ping.** Today,
  `MailBalancer::resendQuotas()` gets real quota/rate-limit numbers via a
  separate synthetic `/emails/batch` ping (cached 5 min) — the actual send
  path (`Illuminate\Mail\Transport\ResendTransport` via the `resend/resend-php`
  SDK) discards the HTTP response headers where that data lives, so no
  per-send quota signal reaches the app today.
- **Make it a genuinely OS-level component**, e.g. a small daemon/service or a
  shared SQLite/file store under `/opt/deploy/` outside either app's own
  database, so staging and prod both read/write the same counters — matching
  how Postfix itself is already a single shared system service.
- **Feed the admin dashboard** ("Email Sending Quota" panel) from that shared,
  live-queried state instead of (or in addition to) the durable per-app
  `MailSendStat` table.
- **Selection strategy:** replace "least-used-so-far" with a looser
  round-robin — one send to each provider in turn before repeating — or a
  random choice weighted by each provider's remaining capacity, so a burst
  naturally spreads without needing perfectly accurate live counts.

**Status:** Deferred. Current fix (2026-09-14): `MailBalancer` now derives its
per-provider daily counts and rotation purely from the durable `MailSendStat`
table (survives a deploy's `artisan optimize:clear`, which previously reset
everything to zero every deploy) — this closed the "resets on every deploy"
bug but not the "staging and prod don't see each other's usage" gap described
above.

## Offsite backup via Google Drive (deferred)

**Idea:** Automate offsite backup to Google Drive instead of manual SFTP.
- Pros: No external server to manage, OAuth-based auth, familiar UI
- Cons: Rate limits, quota management, requires Google account
- Decision: Use SFTP drop-box (ecb.pm) instead for now (more control, simpler ops)
- If revisited: Use rclone + Google Drive API with incremental syncs

**Status:** Deferred. Current approach: SFTP to ecb.pm drop-box.
