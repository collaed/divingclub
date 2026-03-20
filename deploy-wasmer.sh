#!/usr/bin/env bash
set -euo pipefail

echo "=== DivingClub → Wasmer Edge Deploy ==="
echo ""

# Check wasmer CLI is installed
if ! command -v wasmer &> /dev/null; then
    echo "✗ Wasmer CLI not found. Installing..."
    curl https://get.wasmer.io -sSfL | sh
    export PATH="$HOME/.wasmer/bin:$PATH"
fi

echo "→ Wasmer $(wasmer --version)"

# 1. Install PHP dependencies
echo "→ Installing PHP dependencies (no-dev)..."
composer install --no-dev --optimize-autoloader --quiet

# 2. Build frontend assets
echo "→ Building frontend assets..."
npm ci --silent 2>/dev/null && npm run build

# 3. Laravel production prep
echo "→ Clearing Laravel caches..."
php artisan config:clear --quiet
php artisan route:clear --quiet
php artisan view:clear --quiet

# 4. Deploy
echo "→ Deploying to Wasmer Edge..."
wasmer deploy --non-interactive

echo ""
echo "=== ✓ Deploy complete! ==="
echo ""
echo "Useful commands:"
echo "  wasmer app logs              — View live logs"
echo "  wasmer app info              — App URL and details"
echo "  wasmer app secrets list      — List configured secrets"
echo "  wasmer app versions list     — Deployment history"
