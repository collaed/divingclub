#!/bin/bash
# ============================================================================
# Postfix MX Cutover — clubcep.eu
# 
# Run on VPS (204.168.168.60) as root.
# Configures Postfix to accept mail for @clubcep.eu (in addition to
# @test.clubcep.eu) and deliver everything to the Laravel pipe.
#
# Prerequisites:
#   - Postfix already installed and active (confirmed)
#   - laravel-pipe transport already configured in master.cf (confirmed)
#   - Port 25 open in UFW (confirmed)
#   - Caddy running for HTTPS (confirmed)
#
# After running this script, you MUST:
#   1. Update DNS: MX clubcep.eu → 204.168.168.60
#   2. Update SPF: add ip4:204.168.168.60
#   3. Update .env: CLUB_MAIL_ADDRESS=cep@clubcep.eu
#
# Author: Eddy Collart — 2026-07-31
# ============================================================================

set -euo pipefail

echo "=== Postfix MX Cutover for clubcep.eu ==="
echo ""

# ─── 1. TLS Certificate for SMTP ─────────────────────────────────────────────
echo "[1/5] Obtaining Let's Encrypt certificate for mail.clubcep.eu..."

# Check if certbot is installed
if ! command -v certbot &>/dev/null; then
    echo "  Installing certbot..."
    apt-get update -qq && apt-get install -y -qq certbot
fi

# Get cert using standalone (port 80 — Caddy must not be on this domain)
# If mail.clubcep.eu doesn't have a Caddy config, standalone works.
# Otherwise use --webroot with Caddy's root.
if [ ! -f /etc/letsencrypt/live/mail.clubcep.eu/fullchain.pem ]; then
    # Stop caddy briefly for standalone verification on mail.clubcep.eu
    # (only if mail.clubcep.eu A record points here)
    certbot certonly --standalone -d mail.clubcep.eu --non-interactive --agree-tos \
        --email eddy.collart@gmail.com --preferred-challenges http \
        || echo "  WARN: certbot failed — DNS may not point here yet. Using test.clubcep.eu cert as fallback."
fi

# Use whichever cert is available
if [ -f /etc/letsencrypt/live/mail.clubcep.eu/fullchain.pem ]; then
    CERT=/etc/letsencrypt/live/mail.clubcep.eu/fullchain.pem
    KEY=/etc/letsencrypt/live/mail.clubcep.eu/privkey.pem
elif [ -f /etc/letsencrypt/live/test.clubcep.eu/fullchain.pem ]; then
    CERT=/etc/letsencrypt/live/test.clubcep.eu/fullchain.pem
    KEY=/etc/letsencrypt/live/test.clubcep.eu/privkey.pem
else
    echo "  No Let's Encrypt cert available. Keeping snakeoil for now."
    CERT=/etc/ssl/certs/ssl-cert-snakeoil.pem
    KEY=/etc/ssl/private/ssl-cert-snakeoil.key
fi

echo "  Using cert: $CERT"

# ─── 2. Postfix main.cf updates ──────────────────────────────────────────────
echo "[2/5] Updating Postfix main.cf..."

# Add clubcep.eu to mydestination
postconf -e "mydestination = clubcep.eu, test.clubcep.eu, localhost"

# Set hostname
postconf -e "myhostname = mail.clubcep.eu"

# Configure TLS with real cert
postconf -e "smtpd_tls_cert_file = $CERT"
postconf -e "smtpd_tls_key_file = $KEY"
postconf -e "smtpd_tls_security_level = may"

# Catch-all: deliver everything for @clubcep.eu to the pipe
# Using transport_maps to route clubcep.eu to laravel-pipe
cat > /etc/postfix/transport <<'EOF'
clubcep.eu      laravel-pipe:
test.clubcep.eu laravel-pipe:
EOF
postmap /etc/postfix/transport
postconf -e "transport_maps = hash:/etc/postfix/transport"

# Accept mail for any local part (no recipient rejection)
postconf -e "local_recipient_maps ="

# Ensure recipient_delimiter supports plus-addressing
postconf -e "recipient_delimiter = +"

echo "  Done."

# ─── 3. Virtual alias maps (catch-all) ───────────────────────────────────────
echo "[3/5] Configuring virtual alias maps (catch-all → pipe)..."

# Not needed since transport_maps handles routing to laravel-pipe
# But clear any stale virtual maps
postconf -e "virtual_alias_maps ="

echo "  Done (transport_maps handles routing)."

# ─── 4. Restart Postfix ──────────────────────────────────────────────────────
echo "[4/5] Restarting Postfix..."
systemctl restart postfix
sleep 2

if systemctl is-active --quiet postfix; then
    echo "  Postfix is active."
else
    echo "  ERROR: Postfix failed to start!"
    journalctl -u postfix --no-pager -n 10
    exit 1
fi

# ─── 5. Verify ───────────────────────────────────────────────────────────────
echo "[5/5] Verification..."
echo ""
echo "  Postfix config:"
postconf mydestination myhostname transport_maps smtpd_tls_cert_file | sed 's/^/    /'
echo ""
echo "  Transport map:"
postmap -q "clubcep.eu" hash:/etc/postfix/transport | sed 's/^/    /' || echo "    (no match — check transport file)"
echo ""
echo "  Listening on port 25:"
ss -tlnp | grep ":25 " | sed 's/^/    /'
echo ""

# Test: send a message to the pipe
echo "Testing pipe delivery..."
echo "Cutover test $(date)" | /usr/bin/php /opt/deploy/apps/divingclub/artisan mail:inbound \
    --to="bureau@clubcep.eu" --from="system@clubcep.eu" --subject="MX Cutover Test" 2>&1 | sed 's/^/    /'

echo ""
echo "=== DONE ==="
echo ""
echo "NEXT STEPS (manual):"
echo ""
echo "1. DNS CHANGES (at topdns.com / heberg.ch panel):"
echo "   - Add/update A record:  mail.clubcep.eu → 204.168.168.60"
echo "   - Update MX record:     clubcep.eu MX 10 mail.clubcep.eu"
echo "   - Update SPF TXT:       v=spf1 include:spf.mailjet.com a mx ip4:204.168.168.60 ip4:54.39.163.81 ~all"
echo "   - Set TTL to 300 (5 min) during transition"
echo ""
echo "2. UPDATE .env on VPS:"
echo "   ssh root@204.168.168.60"
echo "   sed -i 's|CLUB_MAIL_ADDRESS=.*|CLUB_MAIL_ADDRESS=cep@clubcep.eu|' /opt/deploy/apps/divingclub/.env"
echo "   cd /opt/deploy/apps/divingclub && php artisan config:clear"
echo ""
echo "3. VERIFY after DNS propagation (check with: dig MX clubcep.eu +short):"
echo "   - Send email to bureau@clubcep.eu from external account"
echo "   - Check: tail -5 /var/log/mail.log"
echo "   - Check: php artisan tinker --execute=\"App\\Models\\EmailLog::latest()->first()->subject;\""
echo ""
echo "4. CERTBOT RENEWAL (add to cron if not already):"
echo "   echo '0 3 * * * certbot renew --quiet --deploy-hook \"systemctl reload postfix\"' | crontab -"
echo ""
echo "5. ROLLBACK if issues:"
echo "   - Revert MX to: clubcep.eu MX 10 clubcep.eu (old host at 142.4.216.50)"
echo "   - TTL of 300 means recovery in 5 minutes"
