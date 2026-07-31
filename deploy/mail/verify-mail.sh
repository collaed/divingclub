#!/bin/bash
# Quick verification after MX cutover
# Run from anywhere with dig/host installed

echo "=== Mail Cutover Verification ==="
echo ""

echo "1. MX Record:"
dig MX clubcep.eu +short
echo ""

echo "2. mail.clubcep.eu A Record:"
dig A mail.clubcep.eu +short
echo ""

echo "3. SPF Record:"
dig TXT clubcep.eu +short | grep spf
echo ""

echo "4. DMARC:"
dig TXT _dmarc.clubcep.eu +short
echo ""

echo "5. SMTP connectivity test (port 25):"
if command -v nc &>/dev/null; then
    echo "QUIT" | timeout 5 nc -w3 mail.clubcep.eu 25 2>/dev/null | head -1
elif command -v telnet &>/dev/null; then
    echo "Trying telnet..."
    echo "QUIT" | timeout 5 telnet mail.clubcep.eu 25 2>&1 | head -3
else
    echo "  (nc/telnet not available — use: openssl s_client -connect mail.clubcep.eu:25 -starttls smtp)"
fi
echo ""

echo "6. STARTTLS check:"
echo "QUIT" | timeout 5 openssl s_client -connect mail.clubcep.eu:25 -starttls smtp 2>/dev/null | grep -E "subject=|issuer=|Verify" | head -3
echo ""

echo "=== Expected results ==="
echo "  MX: 10 mail.clubcep.eu."
echo "  A:  204.168.168.60"
echo "  SPF: contains ip4:204.168.168.60"
echo "  SMTP: 220 mail.clubcep.eu ESMTP"
echo "  TLS: subject with clubcep.eu or test.clubcep.eu"
