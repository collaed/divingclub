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
- [ ] Register new member → email verification
- [ ] Login/logout
- [ ] Password reset flow
- [ ] Role-based access: member vs instructor vs bureau_master
- [ ] Social login (Google/Facebook) if configured

### Member Management
- [ ] Admin creates member manually
- [ ] Member edits profile (name, phone, emergency contact)
- [ ] Cotisation years checkboxes (current year shows unpaid badge if unchecked)
- [ ] Medical certificate upload + expiry tracking
- [ ] Certification level assignment
- [ ] Member directory (visible to logged-in members)
- [ ] GDPR data export/deletion

### Events & Calendar
- [ ] Create season with weekly patterns
- [ ] Generate events from season
- [ ] Event registration (confirm/waitlist)
- [ ] Event check-in/check-out
- [ ] Email all event participants (Admin → Email → group "event")
- [ ] Federated event (mark as federated, set external slots)
- [ ] WhatsApp group link on event

### Dive Planning
- [ ] Dive group planner validates rules per federation
- [ ] 39 rules across 5 federations (global, FFESSM, LIFRAS, PADI, BSAC)
- [ ] Medical compliance check (LIFRAS calendar-based expiry)
- [ ] Instructor availability calendar

### Payments
- [ ] Fee calculation with age discounts
- [ ] Fee components (base + insurance + double affiliation)
- [ ] Bank statement import (CAMT.053)
- [ ] Payment reconciliation
- [ ] Unpaid member badge

### Articles & CMS
- [ ] Create article (news, newsletter, video, safety doc)
- [ ] Translation tabs on article view
- [ ] Auto-translation (hourly scheduled task or admin button)
- [ ] User's preferred locale auto-selects correct tab
- [ ] Homepage excludes articles with sort_order < 0

### Email System
- [ ] Create email template with variables ({{first_name}}, {{club_name}})
- [ ] Send to group (all, active, instructors, bureau, event participants)
- [ ] Bilingual email (original + translated version appended)
- [ ] Email log with status tracking
- [ ] Verify with Mailpit/log driver

### Equipment
- [ ] Equipment inventory CRUD
- [ ] Maintenance rules (service intervals)
- [ ] Equipment reservation per event

### Voting
- [ ] Create vote (single choice, multiple choice, ranked)
- [ ] Token-based voting (no login required)
- [ ] Auto-open/close by schedule
- [ ] Results display

### Multi-Club & Theming
- [ ] Theme presets (Ocean, Coral, Lagoon, Abyss, Tropical, Arctic)
- [ ] Custom colors and logo upload
- [ ] Club name in header/footer/emails
- [ ] License system (RSA-SHA256)

### Inter-Club Federation
- [ ] Create partnership with key exchange
- [ ] Browse partner's federated events
- [ ] External registration via API
- [ ] Approve/reject external registrations
- [ ] Cancel external registration

### Install Wizard
- [ ] Fresh install redirects to /install
- [ ] SQLite option creates database.sqlite
- [ ] MySQL option validates connection before proceeding
- [ ] Migrations + seeds run successfully
- [ ] Admin account created and functional
- [ ] /install inaccessible after installation

## 5. Automated Tests

```bash
# Run all tests
php artisan test

# Run critical paths only
php artisan test --filter=CriticalPathsTest

# Current: 16 tests, 28 assertions
```

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
