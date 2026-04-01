# DivingClub-Manager — Requirements v2

## Runtime

- **PHP** 8.3+ with extensions: pdo_pgsql/pdo_mysql, gd, intl, zip, mbstring, xml, bcmath, gmp, opcache, pcntl, imap (optional, for IMAP inbound mode)
- **Database**: PostgreSQL 14+ (production), MySQL 8+ (dev), SQLite (testing)
- **Redis** for queue processing (Horizon)
- **Node.js** 18+ for asset building (Vite)
- **Postfix** (or any MTA) for inbound mail — standard plus-addressing, zero config needed

## Key Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| laravel/framework | v11 | Core framework |
| spatie/laravel-permission | v6 | Role & permission management (6 roles, 12 permissions) |
| laravel/horizon | v5 | Redis queue monitoring dashboard |
| intervention/image | v4 | Image manipulation (thumbnails, avatar resize) |
| resend/resend-laravel | v1 | Email delivery via Resend API |
| barryvdh/laravel-dompdf | v3 | PDF generation (fiche de sécurité) |
| endroid/qr-code | v5 | SEPA QR codes, vCards, federation QR |
| minishlink/web-push | v10 | Push notifications |
| ezyang/htmlpurifier | v4 | HTML sanitization |
| apereo/phpcas | v1 | EU Login (CAS) authentication |

## Roles & Permissions (spatie/laravel-permission)

**Roles:** public, member, instructor, bureau_finance, bureau_technical, bureau_master

**Permissions:**
- `manage members`, `manage events`, `manage equipment`, `manage articles`
- `manage payments`, `manage settings`, `send newsletters`, `manage backups`
- `view audit logs`, `manage dive sites`, `manage votes`, `impersonate users`

Bureau Master has all permissions. Bureau Finance/Technical have a subset. Instructors can manage events and dive sites.

## Email System

### Outbound (Resend API)
- Configured via `MAIL_MAILER=resend` and `RESEND_KEY`
- Load-balancing across two API keys (primary + secondary) for 200 emails/day on free tier
- `Mail::alwaysTo()` in staging mode redirects all outbound to a single address

### Inbound (Mail Aliases)
- **Plus-addressing**: one mailbox handles all aliases (`clubcep+bureau@`, `clubcep+event.42@`, etc.)
- **Two modes**: Maildir (local, zero config) or IMAP (remote mailbox)
- **Aliases**: bureau, instructors, members, event.{id}, members.pn1/pn2/pn3, year={YYYY}, name lookup
- **Subject directives**: `(recipients: bureau, sortie=42, simulate)`
- **Authorization**: bureau/instructors only, unknown senders rejected
- **Logging**: all forwarded messages appear in event Communications section

### Configuration
```env
CLUB_MAIL_ADDRESS=clubcep@yourdomain.com
INBOUND_MAIL_ENABLED=true
INBOUND_MAIL_MODE=maildir
INBOUND_MAILDIR=/home/clubcep/Maildir
```

## Newsletter System

- 5-slot compose UI with article picker and type filtering
- Per-slot editable teaser text and optional custom URL
- 25 SVG marine decorations with scatter button
- Email-safe table layout with sliced decorative images
- "Send test to me" button for one-click preview
- "Send for Comments" mailto to bureau members
- Approval workflow (3 bureau members must approve)
- Bilingual support (EN link in each card)
- Configurable article base URL for external sites

## Translation System

- 15 locales: en, fr, de, lb, pt, it, nl, es, pl, hu, ro, el, et, sk, fi
- 631 JSON translation keys per locale (UI strings)
- 940 PHP translation keys per locale (messages)
- Article auto-translation via Google Translate API
- Source hash tracking, word count validation, retry logic, auto-flagging
- Hourly scheduled job refreshes stale translations

## Scheduled Tasks (8 jobs, monitored via Horizon + heartbeat)

| Job | Schedule | Purpose |
|-----|----------|---------|
| SendMedicalReminders | Daily 08:00 | Expiry reminders at 30/15/7/0 days |
| WeeklyBackup | Sunday 03:00 | Full DB + files backup |
| ProcessTranslations | Hourly | New articles + stale refresh + quality flags |
| AutoOpenCloseVotes | Every minute | Draft→open, open→closed at scheduled times |
| PurgeAuditLogs | Monthly | Retention policy cleanup |
| CleanupClassifieds | Monthly | Unpublish expired, delete after 3 months |
| SendEquipmentReminders | Daily 09:00 | Overdue loan notifications |
| PollInboundMail | Every minute | Process inbound alias emails |

## Auto-Update System

- GitHub API version check (cached 6h)
- One-click update from admin dashboard: git pull → composer → npm → migrate → cache clear
- Bureau Master only

## Infrastructure (staging: test.clubcep.eu)

- **Hetzner VPS**: Ubuntu 24.04, PHP 8.3-FPM, Caddy, PostgreSQL, Redis
- **Supervisor**: manages Horizon daemon
- **Postfix**: local mail delivery (plus-addressing, zero custom config)
- **App user**: `clubcep` — no sudo, no root, no docker access
- **Staging mode**: `STAGING_MODE=true`, `MAIL_ALWAYS_TO=admin@gmail.com`
