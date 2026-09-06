#!/usr/bin/env bash
#
# Deploy the DB-driven dues / status-sets / age-taper / QR-retirement feature
# set to the Hetzner STAGING server and run migrations there.
#
# It ships ONLY the files that belong to this feature (explicit allowlist), so
# unrelated in-flight work in the working tree is not pushed.
#
# Usage:
#   bash deploy/deploy-dues-staging.sh            # deploy + migrate
#   DRY_RUN=1 bash deploy/deploy-dues-staging.sh  # show what would happen
#
# Server (see .kiro deployment steering):
#   SSH   root@204.168.168.60
#   App   /opt/deploy/apps/divingclub
#   User  clubcep  (PHP-FPM pool)  — artisan must run as this user
#
set -euo pipefail

SSH_HOST="root@204.168.168.60"
APP_PATH="/opt/deploy/apps/divingclub"
APP_USER="clubcep"
DRY_RUN="${DRY_RUN:-0}"

# --- Files to deploy (relative to repo root) -------------------------------
FILES=(
  # Migrations (new)
  "database/migrations/2026_09_06_100000_create_status_sets.php"
  "database/migrations/2026_09_06_110000_add_component_taper_and_fee_season_fk.php"
  "database/migrations/2026_09_06_120000_add_provisional_to_payment_expected.php"
  "database/migrations/2026_09_06_130000_add_kind_to_fee_components.php"

  # Models
  "app/Models/MemberStatus.php"
  "app/Models/StatusSet.php"
  "app/Models/User.php"
  "app/Models/MembershipFee.php"
  "app/Models/MembershipFeeComponent.php"
  "app/Models/PaymentExpected.php"

  # Services
  "app/Services/FeeCalculationService.php"
  "app/Services/MailAliasService.php"
  "app/Services/LicenceResolver.php"
  "app/Services/LicenceDerivation.php"
  "app/Services/FlassaState.php"

  # Requests
  "app/Http/Requests/StoreFeeComponentRequest.php"
  "app/Http/Requests/UpdateFeeComponentRequest.php"
  "app/Http/Requests/CalculateDuesRequest.php"

  # Controllers
  "app/Http/Controllers/DuesCalculatorController.php"
  "app/Http/Controllers/QrCodeController.php"
  "app/Http/Controllers/ProfileController.php"
  "app/Http/Controllers/MembersDirectoryController.php"
  "app/Http/Controllers/Admin/PaymentController.php"
  "app/Http/Controllers/Admin/SettingsController.php"
  "app/Http/Controllers/Admin/MemberController.php"
  "app/Http/Controllers/Admin/VoteGroupController.php"

  # Seeders
  "database/seeders/MemberStatusSeeder.php"
  "database/seeders/StatusSetSeeder.php"
  "database/seeders/DatabaseSeeder.php"
  "database/seeders/Fee2027Seeder.php"
  "database/seeders/CepSeeder.php"

  # Routes
  "routes/web.php"
  "routes/admin.php"

  # Views
  "resources/views/dues-calculator.blade.php"
  "resources/views/components/layout.blade.php"
  "resources/views/admin/payments/components.blade.php"
  "resources/views/admin/settings/index.blade.php"
  "resources/views/admin/members/index.blade.php"
  "resources/views/profile/show.blade.php"
  "resources/views/profile/tabs/info.blade.php"
  "resources/views/events/show.blade.php"

  # Frontend (dues-live.js is bundled into the built app.js)
  "resources/js/app.js"
  "resources/js/dues-live.js"
)

# Views removed on the server (payment QR retirement)
DELETE_ON_SERVER=(
  "resources/views/cotisation.blade.php"
  "resources/views/payment-verify.blade.php"
)

run() {
  if [[ "$DRY_RUN" == "1" ]]; then
    echo "DRY: $*"
  else
    eval "$@"
  fi
}

echo "==> Verifying local files exist"
for f in "${FILES[@]}"; do
  [[ -f "$f" ]] || { echo "MISSING local file: $f" >&2; exit 1; }
done

echo "==> Copying ${#FILES[@]} files to $SSH_HOST:$APP_PATH"
for f in "${FILES[@]}"; do
  remote_dir="$APP_PATH/$(dirname "$f")"
  run "ssh $SSH_HOST 'mkdir -p \"$remote_dir\"'"
  run "scp -q '$f' '$SSH_HOST:$APP_PATH/$f'"
  echo "    + $f"
done

echo "==> Removing retired views on server"
for f in "${DELETE_ON_SERVER[@]}"; do
  run "ssh $SSH_HOST 'rm -f \"$APP_PATH/$f\"'"
  echo "    - $f"
done

echo "==> Deploying built frontend assets (public/build)"
# Ship the whole build dir so the hashed manifest and asset filenames stay in sync.
run "ssh $SSH_HOST 'mkdir -p \"$APP_PATH/public/build\"'"
run "scp -rq public/build/manifest.json public/build/assets '$SSH_HOST:$APP_PATH/public/build/'"

echo "==> Fixing ownership (must be $APP_USER, not root)"
run "ssh $SSH_HOST 'chown -R $APP_USER:$APP_USER \"$APP_PATH/app\" \"$APP_PATH/database\" \"$APP_PATH/routes\" \"$APP_PATH/resources\" \"$APP_PATH/public/build\"'"

echo "==> Running migrations (as $APP_USER) — status BEFORE:"
# migrate:status hits a cosmetic Termwind mb_strimwidth error on this server
# (mbstring polyfill gap); it must not abort the deploy, so tolerate failure.
run "ssh $SSH_HOST 'sudo -u $APP_USER php $APP_PATH/artisan migrate:status 2>/dev/null | tail -15 || true'"

echo "==> php artisan migrate --force (as $APP_USER)"
run "ssh $SSH_HOST 'sudo -u $APP_USER php $APP_PATH/artisan migrate --force'"

echo "==> Seeding statuses + status sets + 2027 fees (idempotent, as $APP_USER)"
run "ssh $SSH_HOST 'sudo -u $APP_USER php $APP_PATH/artisan db:seed --class=MemberStatusSeeder --force'"
run "ssh $SSH_HOST 'sudo -u $APP_USER php $APP_PATH/artisan db:seed --class=StatusSetSeeder --force'"
run "ssh $SSH_HOST 'sudo -u $APP_USER php $APP_PATH/artisan db:seed --class=Fee2027Seeder --force'"

echo "==> Clearing caches (as $APP_USER)"
run "ssh $SSH_HOST 'sudo -u $APP_USER php $APP_PATH/artisan optimize:clear'"
run "ssh $SSH_HOST 'sudo -u $APP_USER php $APP_PATH/artisan view:clear'"
run "ssh $SSH_HOST 'sudo -u $APP_USER php $APP_PATH/artisan route:clear'"

echo "==> Re-fixing ownership after cache regeneration"
run "ssh $SSH_HOST 'chown -R $APP_USER:$APP_USER \"$APP_PATH/bootstrap/cache\" \"$APP_PATH/storage\"'"

echo "==> Done. Verify: https://test.clubcep.eu/dues"
