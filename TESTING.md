# DivingClub Manager — Testing Manual

## 1. Quick Start (Single Instance)

```bash
git clone https://github.com/collaed/divingclub.git
cd divingclub
composer install
# Visit http://localhost:8000/install → choose SQLite, set admin credentials
php artisan serve
```

## 2. Email Testing Without Sending

### Option A: Laravel Log Driver (recommended)
In `.env`:
```
MAIL_MAILER=log
```
All emails are written to `storage/logs/laravel.log`. Tail it:
```bash
tail -f storage/logs/laravel.log | grep -A 30 "Message-ID"
```

### Option B: Mailpit (local SMTP trap)
```bash
# Install Mailpit (catches all outbound SMTP)
docker run -d -p 8025:8025 -p 1025:1025 axllent/mailpit
```
In `.env`:
```
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_ENCRYPTION=null
```
Open http://localhost:8025 to see all captured emails with full HTML rendering.

### Option C: /var/mail (system mail)
```
MAIL_MAILER=sendmail
```
Then monitor:
```bash
tail -f /var/mail/$(whoami)
# or
sudo tail -f /var/spool/mail/www-data
```

### Option D: MailHog (alternative to Mailpit)
```bash
docker run -d -p 8025:8025 -p 1025:1025 mailhog/mailhog
```
Same `.env` as Mailpit.

## 3. Multi-Instance Federation Testing (VirtualBox)

### Setup: Two VMs Simulating Two Clubs

#### VM1: "Club Européen de Plongée" (CEP)
```bash
# VirtualBox: Ubuntu 24.04, NAT + Host-Only adapter (192.168.56.101)
sudo apt install php8.3 php8.3-{sqlite3,mbstring,xml,curl,zip} composer nginx
git clone https://github.com/collaed/divingclub.git /var/www/cep
cd /var/www/cep && composer install
# Visit http://192.168.56.101/install → SQLite, name "CEP Luxembourg"
```

#### VM2: "Plongée Alsace" (partner club)
```bash
# VirtualBox: Ubuntu 24.04, NAT + Host-Only adapter (192.168.56.102)
# Same install steps, name "Plongée Alsace"
```

#### Nginx config (both VMs):
```nginx
server {
    listen 80;
    root /var/www/cep/public;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Federation Pairing Walkthrough

1. **On VM1 (CEP):** Admin → Partnerships → Add Partner
   - Name: "Plongée Alsace"
   - Base URL: `http://192.168.56.102`
   - Copy the generated Key ID and Secret

2. **On VM2 (Alsace):** Admin → Partnerships → Add Partner
   - Name: "CEP Luxembourg"
   - Base URL: `http://192.168.56.101`
   - Copy the generated Key ID and Secret
   - Paste VM1's Key ID + Secret into "Their credentials"

3. **On VM1:** Edit the partnership, add VM2's credentials as "Their credentials"

4. **On VM1:** Create an event, check "Federated", set external_slots = 5

5. **On VM2:** Admin → Partnerships → "Browse Events" on CEP → see the federated event

6. **Test API directly:**
```bash
# From VM2, list VM1's federated events:
curl -H "X-Club-Key-Id: dc_xxxxx" -H "X-Club-Secret: yyyyyy" \
  http://192.168.56.101/api/federation/events

# Register an external member:
curl -X POST -H "X-Club-Key-Id: dc_xxxxx" -H "X-Club-Secret: yyyyyy" \
  -H "Content-Type: application/json" \
  -d '{"event_id":1,"member_name":"Jean Dupont","cert_level":"FFESSM N2","medical_valid_until":"2027-01-31"}' \
  http://192.168.56.101/api/federation/register
```

7. **On VM1:** Admin → Partnerships → External Registrations → Approve/Reject

### Alternative: Docker Compose (simpler)
```yaml
# docker-compose.yml
services:
  club-a:
    build: .
    ports: ["8001:80"]
    environment:
      APP_URL: http://localhost:8001
      DB_CONNECTION: sqlite
  club-b:
    build: .
    ports: ["8002:80"]
    environment:
      APP_URL: http://localhost:8002
      DB_CONNECTION: sqlite
  mailpit:
    image: axllent/mailpit
    ports: ["8025:8025", "1025:1025"]
```

## 4. Feature Testing Checklist

### Authentication & Roles
| # | Action | Expected Result |
|---|--------|-----------------|
| 1 | Register with email/password | Verification email sent (check Mailpit/log). Redirect to "verify email" page |
| 2 | Click verification link | Redirect to profile. Flash "Email verified" |
| 3 | Login with valid credentials | Redirect to home. Nav shows member menu |
| 4 | Login with wrong password | "Invalid credentials" error. No redirect |
| 5 | Password reset | Email with reset link. New password works |
| 6 | Access /admin as member | 403 Forbidden |
| 7 | Access /admin as bureau_master | Admin dashboard loads with stats |
| 8 | Social login (Google) | Redirect to Google, back to profile on success |

### Member Management
| # | Action | Expected Result |
|---|--------|-----------------|
| 1 | Admin creates member manually | User appears in member list. Email sent with temp password |
| 2 | Member edits profile | Changes saved. Flash "Profile updated" |
| 3 | Toggle cotisation year checkbox | Year badge appears/disappears. Unpaid badge shows if current year unchecked |
| 4 | Upload medical certificate | File stored. Status shows "Not verified". Expiry calculated |
| 5 | Bureau verifies medical cert | Status changes to "Verified". Green badge |
| 6 | Assign certification level | Level appears on diving tab. Dive planner uses it |
| 7 | GDPR data export | JSON file downloads with all personal data |
| 8 | GDPR data deletion | Account anonymized. Personal fields cleared |

### Events & Calendar
| # | Action | Expected Result |
|---|--------|-----------------|
| 1 | Create season + patterns | Season appears in list. Patterns shown |
| 2 | Generate events | Events created (count matches non-holiday dates). Flash "X events created" |
| 3 | Register for event | Status "Confirmed". Participant count increments |
| 4 | Register when event full | Status "Waitlisted". Position shown |
| 5 | Cancel registration | Status removed. If waitlist exists, next person auto-promoted |
| 6 | Check-in at event | Timestamp recorded. Badge shows "Present" |
| 7 | Email event participants | Emails sent to all confirmed registrants (verify in Mailpit) |
| 8 | Mark event as federated | `is_federated=true`. Appears in `/api/federation/events` |

### Dive Planning
| # | Action | Expected Result |
|---|--------|-----------------|
| 1 | Plan dive with PE-20 diver, no guide | Error: "PE-20 requires GP-N4+ guide" |
| 2 | Plan dive with PE-20 + GP-N4 guide | Valid group. Max depth 20m shown |
| 3 | Plan dive with PA-20 diver age 15 | Error: "PA-12/PA-20 autonomous requires minimum age 16" |
| 4 | Plan dive with expired medical | Warning: "Medical certificate expired for [name]" |
| 5 | LIFRAS P1★ with P3★ leader | Valid. Max depth 20m |
| 6 | BSAC Ocean Diver alone | Error: "Ocean Diver requires Sports Diver+ buddy" |
| 7 | Mixed federation group | Rules applied per diver's federation. Most restrictive depth wins |

### Payments
| # | Action | Expected Result |
|---|--------|-----------------|
| 1 | Calculate fee for "actif" member | Base fee × multiplier + add-ons. Amount shown |
| 2 | Generate payment | Payment record created with unique communication string |
| 3 | Import CAMT.053 bank statement | Transactions parsed. Matched count shown |
| 4 | Auto-match transaction | Communication string matched. Status "Matched" |
| 5 | Confirm match | Payment marked "Paid". Member's unpaid badge removed |

### Articles & CMS
| # | Action | Expected Result |
|---|--------|-----------------|
| 1 | Create news article | Article appears on homepage with blue "News" badge |
| 2 | View article with translations | Tabs shown: Original + translated languages |
| 3 | Click "Generate translations" | Spinner, then tabs populated. 🤖 indicator on auto-translated |
| 4 | Hourly auto-translation runs | Oldest untranslated article gets translations. Check `article_translations` table |
| 5 | User with preferred_locale=ro | Romanian tab auto-selected on article view |
| 6 | Article with sort_order < 0 | Does NOT appear on homepage |

### Email System
| # | Action | Expected Result |
|---|--------|-----------------|
| 1 | Send email with {{first_name}} | Variable replaced with actual name in each email |
| 2 | Send to "all active" group | One email per active member. Count shown in log |
| 3 | Send to "event" group | Only confirmed registrants of selected event receive email |
| 4 | Bilingual email (FR→DE member) | Email body has French original + German translation below separator |
| 5 | Check send log | Each email shows: recipient, subject, status (sent/failed), timestamp |

### Equipment
| # | Action | Expected Result |
|---|--------|-----------------|
| 1 | Create equipment item | Item in inventory with serial number |
| 2 | Loan to member | Loan record created. Item shows "On loan to [name]" |
| 3 | Return equipment | Loan end date set. Item available again |
| 4 | Maintenance overdue | Warning badge on item. Admin notification |

### Voting
| # | Action | Expected Result |
|---|--------|-----------------|
| 1 | Create poll (multi-select, public) | Vote page shows options with checkboxes |
| 2 | Vote via token link | Selections saved. Results shown (if public) |
| 3 | Update vote | Previous selections replaced. Results updated |
| 4 | Election mode: vote | "Irreversible" warning. After submit, cannot change |
| 5 | Election mode: revisit | "You have already voted" message. No results shown |
| 6 | Auto-close by schedule | Vote status changes to "closed" at deadline |

### Multi-Club & Theming
| # | Action | Expected Result |
|---|--------|-----------------|
| 1 | Select "Coral" theme preset | Colors change across all pages. Logo area updated |
| 2 | Upload custom logo | Logo appears in header, footer, favicon, emails |
| 3 | Change club name | Name updated in header, footer, emails, QR codes |
| 4 | Paste license key | "License valid until [date]" shown. Member limit raised |

### Site Layout Variants
| # | Action | Expected Result |
|---|--------|-----------------|
| 1 | Switch to "Professional" layout | Header becomes slim solid bar, no bubbles, nav has grey background with underline indicators, card headers are uppercase |
| 2 | Switch to "Minimal" layout | Header is white with dark text, nav links are pill-shaped, cards are borderless, buttons are pill-shaped |
| 3 | Switch back to "Default" | Gradient header with bubbles returns, standard styling |
| 4 | Toggle dark mode on Professional/Minimal | Dark variants render correctly (appropriate backgrounds, text colors, borders) |
| 5 | Check mobile (< 992px) | Collapsed nav works correctly on all layouts |
| 6 | Submit invalid layout value via devtools | Error flash, layout unchanged |

### Inter-Club Federation
| # | Action | Expected Result |
|---|--------|-----------------|
| 1 | Create partnership | Key ID (dc_xxxxx) + 64-char secret generated |
| 2 | `GET /api/federation/events` with valid headers | JSON array of federated events |
| 3 | `GET /api/federation/events` with wrong secret | 401 Unauthorized |
| 4 | `POST /api/federation/register` | External registration created. Status "pending" |
| 5 | Approve external registration | Status → "approved". Appears on event participant list |
| 6 | Reject external registration | Status → "rejected". Slot freed |
| 7 | Register when external_slots full | 422 "No external slots available" |

### Install Wizard
| # | Action | Expected Result |
|---|--------|-----------------|
| 1 | Visit fresh instance | Auto-redirect to `/install` |
| 2 | Choose SQLite, submit | `.env` created. `database.sqlite` created. Migrations run |
| 3 | Choose MySQL with wrong credentials | Error: "Could not connect to database" |
| 4 | Choose MySQL with valid credentials | `.env` created. Migrations run on MySQL |
| 5 | Admin account created | Can login with provided email/password. Has bureau_master role |
| 6 | Visit `/install` after setup | Redirect to homepage (already installed) |
| 7 | Reference data seeded | 39 dive rules, 110+ cert levels, 11 federations present |

## 5. Automated Tests

```bash
# Run all tests
php artisan test

# Run critical paths only
php artisan test --filter=CriticalPathsTest

# Current: 18 tests, 30 assertions
```

## 6. Scheduled Tasks (Web Cron for Shared Hosting)

If your host doesn't provide cron access, use the built-in web cron endpoint:

### Setup
1. Set `CRON_KEY=your-random-secret` in `.env`
2. Sign up at [cron-job.org](https://cron-job.org) (free)
3. Create a job: `GET https://yoursite.com/cron/run?key=your-random-secret` every 15 minutes

### Test it
```bash
curl "http://localhost:8000/cron/run?key=your-secret"
# Expected: OK 2026-03-18 15:00:00

curl "http://localhost:8000/cron/run?key=wrong"
# Expected: 403 Forbidden
```

### What runs on schedule
| Task | Frequency | What it does |
|------|-----------|-------------|
| Medical reminders | Daily 08:00 | Emails members with expiring certificates |
| Vote auto-open/close | Every minute* | Opens/closes votes at scheduled times |
| Article auto-translation | Hourly | Translates one untranslated article |
| Weekly backup | Sunday 03:00 | Database backup |

*With 15-minute cron-job.org interval, votes may open/close up to 15 minutes late. Acceptable for club use.

## 6. Performance Testing

```bash
# Seed 500 members for load testing
php artisan tinker --execute "
for (\$i = 0; \$i < 400; \$i++) {
    \App\Models\User::factory()->create();
}
echo 'Done';
"

# Check response times
ab -n 100 -c 10 http://localhost:8000/
ab -n 50 -c 5 http://localhost:8000/events
```

## 7. SQLite vs MySQL Comparison

| Aspect | SQLite | MySQL |
|--------|--------|-------|
| Setup | Zero config | Requires server |
| Concurrent writes | ~50/sec | ~5000/sec |
| DB size limit | Practical ~1GB | Unlimited |
| Backup | Copy single file | mysqldump |
| Wasmer compatible | ✅ | ❌ |
| Suitable for | 1-500 members | 500+ members |

## 8. FFESSM Code du Sport Age Requirements (Art. A322-88/89)

These age limits are enforced by the dive group planner:

| Competence | Min Age | Article | Notes |
|------------|---------|---------|-------|
| PE-12 | 12 | — | Supervised, no autonomy |
| PA-12 | 16 | A322-88 | Autonomous ≤12m |
| PA-20 | 16 | A322-88 | Autonomous ≤20m |
| PA-40 | 17 | A322-88 | Autonomous ≤40m |
| PA-60 | 18 | A322-89 | Autonomous ≤60m, requires federation cert |

Source: English translation of Code du Sport from [Gravière du Fort](https://gravieredufort.fr/w/documents-utiles) (updated 07/2025).

## 9. Reference Documents

### Official Sources Used for Rule Implementation
| Document | Source | Used For |
|----------|--------|----------|
| Code du Sport (Art. A322-71 to A322-101) | [legifrance.gouv.fr](https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000006071318/LEGISCTA000018751673/) | FFESSM PE/PA depth zones, age limits, DP requirement |
| Code du Sport English translation | [gravieredufort.fr/w/documents-utiles](https://gravieredufort.fr/w/documents-utiles) | Cross-reference for FFESSM rules |
| MIL 2026 (Manuel d'Instruction LIFRAS) | LIFRAS federation PDF | LIFRAS palanquée table §1.7.1, medical rules §1.5.1 |
| BSAC Safe Diving Guide | [bsac.com](https://www.bsac.com) | Depth limits, buddy requirements, Dive Manager rules |
| BSAC Equivalent Qualifications | bsac.com crossover chart | Federation equivalencies |
| Scuba crossover table | [scubatravel.co.uk](https://www.scubatravel.co.uk) | Multi-agency depth comparison |
| FFESSM MFT (Manuel de Formation Technique) | FFESSM federation PDF | GP-N4 exam curriculum |

### Gravière du Fort Documents (gravieredufort.fr)
The Gravière du Fort site (powered by VPDive) provides practical templates:
- **Fiche de Sécurité** — Standard FFESSM safety sheet template (palanquée log with diver names, levels, gas, planned/actual parameters, DP instructions). Our dive group planner could generate this format.
- **Plan de Secours** — Emergency plan template (SAMU 15 protocol, O2 administration, victim handoff). Useful reference for clubs creating their own emergency plans.
- **Consignes de Sécurité** — Site-specific safety rules.
- **Bathymétrie** — Site bathymetry map. Our dive sites feature could store similar maps.
- **Règlement Intérieur** — Internal rules template for dive sites.


## v1.1.0 Testing Notes

### Spatie Permissions in Tests
Tests using `RefreshDatabase` must create Spatie roles in `setUp()`:
```php
use Spatie\Permission\Models\Role as SpatieRole;

protected function setUp(): void
{
    parent::setUp();
    foreach (['public', 'member', 'instructor', 'bureau_finance', 'bureau_technical', 'bureau_master'] as $r) {
        SpatieRole::findOrCreate($r, 'web');
    }
}
```

When creating test users, assign the Spatie role:
```php
$user = User::factory()->create(['role_id' => $legacyRole->id, 'status_id' => $status->id]);
$user->assignRole('bureau_master');
```

### UserFactory
The factory uses `username` and `primary_email` (not `name` and `email`):
```php
'username' => fake()->userName(),
'primary_email' => fake()->unique()->safeEmail(),
```

### Federation Visibility Tests
4 tests in `FederationVisibilityTest`:
- `test_active_scope_filters_correctly`
- `test_visible_scope_includes_active_and_recognized`
- `test_admin_can_update_federation_visibility`
- `test_new_federation_defaults_to_active`

### Running Tests
```bash
php artisan test --compact                    # All 233 tests
php artisan test --filter=FederationVisibility # Federation tests only
php artisan test --filter=CriticalPath        # Critical path tests
```


### Inbound Mail Testing
Test the full inbound flow on staging:
```bash
# Send to local inbound mailbox
echo "Test body" | mail -s "Test (recipients: bureau)" inbound@localhost

# Check processing (wait ~1 minute for Horizon to pick it up)
php artisan tinker --execute="echo App\Models\EmailLog::where('direction','inbound')->latest()->first()?->status;"

# Or test the artisan command directly
echo "Test body" | php artisan mail:inbound --to=bureau@clubcep.eu --from=eddy.collart@gmail.com --subject="Test"
```

Note: staging mode (`STAGING_MODE=true`) redirects ALL outbound mail to `eddy.collart@gmail.com` via `Mail::alwaysTo()`. Real member emails are never contacted.
