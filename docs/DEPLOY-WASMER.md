# Deploying DivingClub-Manager on Wasmer Edge

Complete guide: from GitHub repo → Wasmer Edge → custom domain with HTTPS → scheduled tasks.

> **Official docs**: https://docs.wasmer.io/edge/guides/laravel

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [GitHub Repository Setup](#2-github-repository-setup)
3. [External MySQL Database](#3-external-mysql-database)
4. [Install Wasmer CLI](#4-install-wasmer-cli)
5. [Wasmer Configuration Files](#5-wasmer-configuration-files)
6. [First Deployment](#6-first-deployment)
7. [Secrets & Environment Variables](#7-secrets--environment-variables)
8. [Wasmer Subdomain (Free)](#8-wasmer-subdomain-free)
9. [Custom Domain & HTTPS](#9-custom-domain--https)
10. [License Key Generation](#10-license-key-generation)
11. [Scheduled Jobs (Lazy Cron)](#11-scheduled-jobs-lazy-cron)
12. [Post-Deployment Job](#12-post-deployment-job)
13. [File Uploads & Storage](#13-file-uploads--storage)
14. [Quick-Deploy Script](#14-quick-deploy-script)
15. [Redeployment](#15-redeployment)
16. [Troubleshooting](#16-troubleshooting)

---

## 1. Prerequisites

- A [wasmer.io](https://wasmer.io) account (free tier works)
- PHP 8.3+ and Composer installed locally
- Node.js 18+ and npm (for building frontend assets)
- Your DivingClub repo on GitHub (public or private)
- An external MySQL 8 database (see step 3)

---

## 2. GitHub Repository Setup

### Option A: Import from GitHub on wasmer.io (easiest)

1. Go to https://wasmer.io/new
2. Click **"Import from GitHub"**
3. If this is your first time, you'll be prompted to install the **Wasmer GitHub App**
   - For a private repo, grant read access to the specific repository
   - For a public repo, no special permissions needed
4. Select your `divingclub` repository
5. Wasmer auto-detects it as a Laravel app and scaffolds the config

### Option B: Deploy from local clone (more control)

```bash
git clone https://github.com/YOUR_USERNAME/divingclub.git
cd divingclub
```

This guide follows Option B since we need to customize configuration.

---

## 3. External MySQL Database

Wasmer Edge is stateless — you need an external MySQL provider. Options:

| Provider | Free Tier | Notes |
|----------|-----------|-------|
| [TiDB Cloud](https://tidbcloud.com/) | 5 GiB | MySQL-compatible, recommended by Wasmer |
| [PlanetScale](https://planetscale.com/) | 5 GiB | MySQL-compatible, serverless |
| [Railway](https://railway.app/) | $5 credit | Real MySQL 8 |
| [Aiven](https://aiven.io/) | Free trial | Managed MySQL |

After provisioning, note down:
- `DB_HOST` (e.g., `gateway01.eu-central-1.prod.aws.tidbcloud.com`)
- `DB_PORT` (e.g., `4000`)
- `DB_DATABASE` (e.g., `divingclub`)
- `DB_USERNAME`
- `DB_PASSWORD`

> **Important**: TiDB is *mostly* MySQL-compatible but has minor differences.
> If you hit issues, Railway gives you real MySQL 8.

### Run migrations against the remote DB

```bash
# Set the remote DB credentials temporarily in .env, then:
php artisan migrate --force
php artisan db:seed
php artisan db:seed --class=CertificationLevelSeeder
php artisan db:seed --class=SampleDataSeeder
```

---

## 4. Install Wasmer CLI

```bash
curl https://get.wasmer.io -sSfL | sh
```

Verify:
```bash
wasmer --version
```

Log in:
```bash
wasmer login
# Opens browser → authorize → token is saved locally
```

---

## 5. Wasmer Configuration Files

### 5a. Build assets first

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

### 5b. Create `wasmer.toml`

This tells Wasmer how to package your app:

```toml
[dependencies]
"php/php" = "=8.3.4"

[fs]
"/app/" = "."

[[command]]
name = "run"
module = "php/php:php"
runner = "wasi"
[command.annotations.wasi]
main-args = ["-t", "/app/public", "-S", "localhost:8080"]
```

### 5c. Create `app.yaml`

This configures your Wasmer Edge deployment:

```yaml
kind: wasmer.io/App.v0
owner: YOUR_WASMER_USERNAME
name: divingclub
package: .
description: "DivingClub-Manager — Diving club management system"

# Debug mode (set to false for production)
debug: false

# Force HTTPS (Wasmer provides free auto-SSL)
redirect:
  force_https: true

# PHP is single-threaded, needs single_concurrency mode
scaling:
  mode: single_concurrency

# Scheduled jobs — see section 11
jobs:
  # Laravel schedule:run every minute (vote open/close)
  - name: schedule-runner
    trigger: '*/1 * * * *'
    action:
      fetch:
        path: /cron/run-schedule?key=YOUR_CRON_SECRET
        timeout: 30s

  # Medical reminders — daily at 08:00 UTC
  - name: medical-reminders
    trigger: '0 8 * * *'
    action:
      fetch:
        path: /cron/medical-reminders?key=YOUR_CRON_SECRET
        timeout: 60s

  # Weekly backup — Sunday 03:00 UTC
  - name: weekly-backup
    trigger: '0 3 * * 0'
    action:
      fetch:
        path: /cron/weekly-backup?key=YOUR_CRON_SECRET
        timeout: 120s

  # Cache warmup after deploy
  - name: post-deploy-warmup
    trigger: post-deployment
    action:
      fetch:
        path: /
        timeout: 30s
```

Replace `YOUR_WASMER_USERNAME` with your wasmer.io username.

### 5d. Create `php.ini` (optional custom PHP settings)

```ini
; Custom PHP settings for Wasmer Edge
memory_limit = 256M
max_execution_time = 60
upload_max_filesize = 10M
post_max_size = 12M
display_errors = Off
```

Then reference it in `wasmer.toml`:
```toml
[command.annotations.wasi]
main-args = ["-c", "/app/php.ini", "-t", "/app/public", "-S", "localhost:8080"]
```

---

## 6. First Deployment

### 🚀 Scriptable — see section 14 for the full script

```bash
# Test locally first
wasmer run .
# Visit http://localhost:8080 to verify

# Deploy to Wasmer Edge
wasmer deploy
```

The CLI will:
1. Package your app as a Wasmer package
2. Upload it to the registry
3. Deploy it to Edge
4. Give you a URL: `https://divingclub-XXXXX.wasmer.app`

---

## 7. Secrets & Environment Variables

Wasmer secrets are injected as environment variables at runtime. Use them for
all sensitive config instead of committing a `.env` file.

### Bulk create from a file

Create `.env.wasmer` (do NOT commit this file):

```env
APP_NAME=DivingClub
APP_ENV=production
APP_KEY=base64:GENERATE_A_NEW_KEY_HERE
APP_DEBUG=false
APP_URL=https://divingclub.wasmer.app

DB_CONNECTION=mysql
DB_HOST=your-tidb-host.com
DB_PORT=4000
DB_DATABASE=divingclub
DB_USERNAME=your_user
DB_PASSWORD=your_password

CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your_mailgun_user
MAIL_PASSWORD=your_mailgun_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME=DivingClub

CLUB_NAME=DivingClub
CLUB_DOMAIN=yourdomain.com
CLUB_ID=CEP

CRON_KEY=GENERATE_A_RANDOM_STRING_HERE
```

Push them all at once:

```bash
wasmer app secrets create --from-file=.env.wasmer
```

### Or create individually

```bash
wasmer app secrets create APP_KEY "base64:$(openssl rand -base64 32)"
wasmer app secrets create DB_HOST "gateway01.eu-central-1.prod.aws.tidbcloud.com"
wasmer app secrets create DB_PASSWORD "your_secure_password"
# ... etc
```

### After setting secrets, redeploy

```bash
wasmer deploy
```

> **Important**: Secrets are NOT available until you redeploy after creating them.

---

## 8. Wasmer Subdomain (Free)

Every Wasmer Edge app automatically gets a free subdomain:

```
https://<app-name>-<hash>.wasmer.app
```

For example: `https://divingclub-a1b2c3.wasmer.app`

This comes with:
- ✅ Free automatic HTTPS/TLS certificate
- ✅ Auto-renewed — no certbot needed
- ✅ Global CDN/Edge distribution

You can also set a cleaner alias. In the Wasmer dashboard:
1. Go to your app → Settings → Domains
2. The primary `*.wasmer.app` domain is already there

**No certbot or Let's Encrypt setup is needed for `*.wasmer.app` domains.**
Wasmer handles all certificate provisioning and renewal automatically.

---

## 9. Custom Domain & HTTPS

### 9a. Add domain in Wasmer dashboard

1. Go to https://wasmer.io → your app → **Settings** → **Domains**
2. Click **"Add Domain"**
3. Enter your domain: e.g., `divingclub.yourdomain.com` or `yourdomain.com`
4. Wasmer shows you the DNS records to create

### 9b. Configure DNS

At your domain registrar (Cloudflare, Namecheap, OVH, etc.), add:

**For a subdomain** (e.g., `divingclub.yourdomain.com`):
```
Type:  CNAME
Name:  divingclub
Value: <your-app>.wasmer.app
```

**For a root/apex domain** (e.g., `yourdomain.com`):
```
Type:  A
Name:  @
Value: <IP provided by Wasmer dashboard>
```

### 9c. HTTPS / SSL Certificate

**Wasmer Edge automatically provisions and renews SSL certificates for custom domains.**

- Uses Let's Encrypt under the hood
- No certbot installation needed
- No cron job for renewal needed
- Certificate is provisioned within minutes of DNS propagation

Just make sure:
- DNS records are correctly pointing to Wasmer
- You wait a few minutes for propagation
- `redirect.force_https: true` is set in `app.yaml` (it's the default)

### 9d. Update APP_URL

After your custom domain is working:

```bash
wasmer app secrets update APP_URL "https://yourdomain.com"
wasmer deploy
```

---

## 10. License Key Generation

DivingClub uses RSA-signed license keys for installations exceeding 100 members.

### Generate RSA key pair (once, keep private key safe!)

```bash
openssl genrsa -out license-private.pem 2048
openssl rsa -in license-private.pem -pubout -out license-public.pem
```

### Update the public key in the source

Edit `app/Services/LicenseService.php` and replace the `PUBLIC_KEY` constant
with the contents of `license-public.pem`.

### Generate a license key

```bash
php scripts/generate-license.php license-private.pem yourdomain.com 500 2027-12-31
```

Output:
```
License Key:
eyJkb21haW4iOi...base64...signature
```

### Install the license

1. Log in as admin
2. Go to Admin → Settings → License tab
3. Paste the license key
4. Save

Or via tinker against your remote DB:
```bash
php artisan tinker --execute "App\Models\ThemeSetting::set('license_key', 'YOUR_LICENSE_KEY_HERE');"
```

> **Keep `license-private.pem` secure and offline.** Never commit it.
> Only `license-public.pem` content goes into the source code.

---

## 11. Scheduled Jobs (Lazy Cron)

Wasmer Edge supports cron-like jobs via `app.yaml`. Since Wasmer apps are
stateless, you can't run `php artisan schedule:run` directly. Instead, use
HTTP-triggered cron endpoints.

### 11a. Add a cron route to your app

The app already has `/cron/run` and the Wasmer-specific routes have been added:
- `/cron/run-schedule` — runs `schedule:run` (votes, audit purge)
- `/cron/medical-reminders` — dispatches medical reminder job
- `/cron/weekly-backup` — dispatches weekly backup job

All are secured by the `CRON_KEY` query parameter (configured in `config/app.php`).

### 11b. Set the cron key on Wasmer

```bash
wasmer app secrets create CRON_KEY "$(openssl rand -hex 32)"
# Note the value — you'll need it in app.yaml
wasmer app secrets reveal CRON_KEY
```

### 11c. Update `app.yaml` jobs with the key

Replace `REPLACE_WITH_CRON_KEY` in the `app.yaml` jobs section with the actual value.

### 11d. Redeploy

```bash
wasmer deploy
```

### Schedule summary

| Job | Cron | What it does |
|-----|------|-------------|
| `schedule-runner` | `*/1 * * * *` | Runs `schedule:run` (vote open/close, audit purge) |
| `medical-reminders` | `0 8 * * *` | Sends medical certificate expiry reminders |
| `weekly-backup` | `0 3 * * 0` | Weekly database backup |

---

## 12. Post-Deployment Job

The `post-deploy-warmup` job in `app.yaml` hits `/` after each deploy to warm
up caches and verify the app is responding. You can extend this:

```yaml
  - name: post-deploy-migrate
    trigger: post-deployment
    action:
      fetch:
        path: /cron/run-schedule?key=YOUR_CRON_SECRET
        timeout: 60s
```

> **Note**: Running `php artisan migrate` on Wasmer Edge requires an execute
> job or SSH access. For safety, run migrations locally against the remote DB
> before deploying.

---

## 13. File Uploads & Storage

Wasmer Edge is stateless — local file storage does not persist across deploys.

### Options

1. **S3-compatible storage** (recommended for production):
   ```bash
   wasmer app secrets create FILESYSTEM_DISK "s3"
   wasmer app secrets create AWS_ACCESS_KEY_ID "your_key"
   wasmer app secrets create AWS_SECRET_ACCESS_KEY "your_secret"
   wasmer app secrets create AWS_DEFAULT_REGION "eu-west-1"
   wasmer app secrets create AWS_BUCKET "divingclub-uploads"
   ```

2. **Wasmer Volumes** (simpler, region-locked):
   Add to `app.yaml`:
   ```yaml
   volumes:
     - name: storage
       mount: /app/storage/app/public
   ```

3. **Commit uploaded files to the repo** (only for small/static files like
   the club logo and article images that rarely change).

---

## 14. Quick-Deploy Script

Save as `deploy-wasmer.sh` in the project root:

```bash
#!/usr/bin/env bash
set -euo pipefail

echo "=== DivingClub → Wasmer Edge Deploy ==="

# 1. Install dependencies
echo "→ Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --quiet

echo "→ Building frontend assets..."
npm ci --silent && npm run build

# 2. Laravel production prep
echo "→ Optimizing Laravel..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 3. Test locally (optional, comment out for CI)
# echo "→ Testing locally with wasmer run..."
# timeout 5 wasmer run . &
# sleep 3 && curl -sf http://localhost:8080 > /dev/null && echo "  Local test OK" || echo "  Local test failed"
# kill %1 2>/dev/null

# 4. Deploy
echo "→ Deploying to Wasmer Edge..."
wasmer deploy --non-interactive

echo ""
echo "=== Deploy complete! ==="
echo "→ Check your app at: https://divingclub.wasmer.app"
echo "→ View logs: wasmer app logs"
```

Make it executable:
```bash
chmod +x deploy-wasmer.sh
```

Run it:
```bash
./deploy-wasmer.sh
```

---

## 15. Redeployment

After code changes:

```bash
# Quick redeploy
wasmer deploy

# Or use the script
./deploy-wasmer.sh
```

After secret changes:
```bash
wasmer app secrets update KEY "new_value"
wasmer deploy   # Required to pick up new secrets
```

Rollback to a previous version:
```bash
wasmer app versions list
wasmer app versions activate <version-id>
```

---

## 16. Troubleshooting

### View logs
```bash
wasmer app logs
```

### Enable debug mode temporarily
```yaml
# app.yaml
debug: true
```
```bash
wasmer deploy
# Check the error, then set debug back to false
```

### Common issues

| Issue | Solution |
|-------|----------|
| "502 Bad Gateway" | Check `wasmer app logs` — likely a PHP fatal error or missing extension |
| DB connection refused | Verify DB_HOST/PORT/PASSWORD secrets; ensure DB allows external connections |
| Assets not loading | Run `npm run build` before deploying; check `public/build/manifest.json` exists |
| "Vite manifest not found" | You forgot `npm run build` before `wasmer deploy` |
| Sessions not persisting | Expected on stateless Edge; use `database` session driver with external DB |
| File uploads disappear | Use S3 or Wasmer Volumes (section 13) |
| Cron jobs not firing | Check job names in `wasmer app logs`; verify CRON_SECRET matches |
| HTTPS not working on custom domain | Wait for DNS propagation (up to 48h); verify CNAME/A records |

### Useful commands
```bash
wasmer app info              # App details and unique URL
wasmer app logs              # Live logs
wasmer app secrets list      # List all secrets
wasmer app secrets reveal --all  # Show secret values
wasmer app versions list     # Deployment history
```

---

## Architecture Summary

```
┌─────────────────────────────────────────────────┐
│                  Wasmer Edge                     │
│                                                  │
│  ┌──────────────┐    ┌───────────────────────┐  │
│  │ divingclub   │    │  Auto-SSL (Let's      │  │
│  │ .wasmer.app  │◄──►│  Encrypt) + CDN       │  │
│  │              │    └───────────────────────┘  │
│  │  PHP 8.3     │                               │
│  │  (WASI)      │    ┌───────────────────────┐  │
│  │              │───►│  Cron Jobs (app.yaml)  │  │
│  └──────┬───────┘    └───────────────────────┘  │
│         │                                        │
└─────────┼────────────────────────────────────────┘
          │
          ▼
┌──────────────────┐    ┌──────────────────┐
│  External MySQL  │    │  S3 / Storage    │
│  (TiDB/Railway)  │    │  (file uploads)  │
└──────────────────┘    └──────────────────┘
```

---

## Quick Reference Card

| Task | Command |
|------|---------|
| Install Wasmer | `curl https://get.wasmer.io -sSfL \| sh` |
| Login | `wasmer login` |
| Test locally | `wasmer run .` |
| Deploy | `wasmer deploy` |
| Set secret | `wasmer app secrets create KEY "value"` |
| Bulk secrets | `wasmer app secrets create --from-file=.env.wasmer` |
| View logs | `wasmer app logs` |
| List versions | `wasmer app versions list` |
| Rollback | `wasmer app versions activate <id>` |
| Generate license | `php scripts/generate-license.php key.pem domain max expires` |
| Generate APP_KEY | `wasmer app secrets create APP_KEY "base64:$(openssl rand -base64 32)"` |
