#!/bin/bash
# ============================================================================
# MX Rollback — revert to old cPanel host
#
# If the cutover fails, run this on the VPS to revert Postfix config,
# then change DNS back manually.
# ============================================================================

set -euo pipefail

echo "=== Rolling back Postfix to test.clubcep.eu only ==="

# Revert mydestination
postconf -e "mydestination = test.clubcep.eu, localhost"
postconf -e "myhostname = laravel.clubcep.eu"

# Revert transport maps to test only
cat > /etc/postfix/transport <<'EOF'
test.clubcep.eu laravel-pipe:
EOF
postmap /etc/postfix/transport

systemctl restart postfix
echo "  Postfix reverted."
echo ""
echo "MANUAL DNS STEPS:"
echo "  1. MX clubcep.eu → 10 clubcep.eu (points to 142.4.216.50 — old host)"
echo "  2. Remove A record for mail.clubcep.eu"
echo "  3. Wait 5 minutes (if TTL was set to 300)"
echo ""
echo "The old cPanel host will resume handling @clubcep.eu mail."
