#!/bin/bash
#
# Hardened production deploy for DivingClub-Manager.
#
# Canonical copy lives in the repo (deploy/auto-deploy.sh); the running copy is
# /opt/deploy/auto-deploy.sh on prod.clubcep.eu. Keep them in sync.
#
# Guarantees:
#   * always runs under a PHP that can actually boot Laravel (php8.3, verified)
#   * wipes bootstrap/cache/*.php BEFORE package:discover, so a stale package
#     manifest can never block artisan from booting (2026-09-08 outage)
#   * fails fast on any step; on failure OR a red post-deploy health check it
#     rolls the working tree + caches back to the previous commit
#   * only restarts Horizon after the app is proven healthy
#
# Re-enable in root's crontab once trusted:
#   0 1 * * * /opt/deploy/auto-deploy.sh >> /var/log/auto-deploy.log 2>&1
#
set -Eeuo pipefail

PHP=/usr/bin/php8.3
COMPOSER="$PHP /usr/local/bin/composer"
APP_USER=clubcep
LOG=/var/log/auto-deploy.log
HEALTH_URL="https://prod.clubcep.eu/health"
KUMA_PUSH="${KUMA_DEPLOY_PUSH:-}"   # optional Uptime-Kuma push URL

log() { echo "$(date '+%F %T') [deploy] $*" | tee -a "$LOG"; }
as_app() { sudo -u "$APP_USER" env -i HOME="/home/$APP_USER" PATH=/usr/local/bin:/usr/bin:/bin "$@"; }

# --- preflight: a working PHP is non-negotiable -------------------------------
[ -x "$PHP" ] || { log "FATAL: $PHP missing"; exit 1; }
for ext in mbstring bcmath intl openssl tokenizer pdo_pgsql; do
    $PHP -m | grep -qix "$ext" || { log "FATAL: $PHP is missing the '$ext' extension — aborting"; exit 1; }
done

deploy_site() {
    local DIR="$1" NAME="$2" SUPERVISOR="$3"
    cd "$DIR"

    local BEFORE AFTER
    BEFORE=$(as_app git rev-parse HEAD)

    as_app git fetch --quiet origin main
    AFTER=$(as_app git rev-parse origin/main)

    if [ "$BEFORE" = "$AFTER" ]; then
        log "[$NAME] no changes ($BEFORE)"
        return 0
    fi

    log "[$NAME] deploying $BEFORE -> $AFTER"

    rollback() {
        log "[$NAME] !!! ROLLBACK to $BEFORE"
        as_app git reset --hard "$BEFORE" || true
        as_app git clean -fd || true
        as_app $COMPOSER install --no-dev --no-interaction --quiet || true
        as_app rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
        as_app $PHP artisan package:discover --quiet || true
        as_app $PHP artisan optimize:clear --quiet || true
        [ -n "$SUPERVISOR" ] && supervisorctl restart "$SUPERVISOR" >/dev/null 2>&1 || true
        [ -n "$KUMA_PUSH" ] && curl -fsS -m 10 "${KUMA_PUSH}?status=down&msg=deploy-rollback-${NAME}" >/dev/null 2>&1 || true
        log "[$NAME] rollback complete"
        exit 1
    }
    trap rollback ERR

    as_app git reset --hard origin/main
    as_app git clean -fd
    as_app $COMPOSER install --no-dev --no-interaction --optimize-autoloader --quiet

    # Generated files — never trust whatever is on disk; regenerate from vendor/.
    as_app rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
    as_app $PHP artisan package:discover --quiet
    as_app $PHP artisan migrate --force --quiet
    as_app $PHP artisan optimize:clear --quiet

    # --- health gate ---------------------------------------------------------
    local code
    code=$(curl -s -o /dev/null -w '%{http_code}' -m 20 "$HEALTH_URL" || echo 000)
    # 200 = healthy, 299 = the app's own "degraded" code (still serving)
    if [ "$code" != "200" ] && [ "$code" != "299" ]; then
        log "[$NAME] health check FAILED (HTTP $code)"
        rollback
    fi
    trap - ERR

    log "[$NAME] deployed OK (health HTTP $code) $(as_app git log --oneline -1)"
    [ -n "$SUPERVISOR" ] && supervisorctl restart "$SUPERVISOR" >/dev/null 2>&1 || true
    [ -n "$KUMA_PUSH" ] && curl -fsS -m 10 "${KUMA_PUSH}?status=up&msg=deployed-${NAME}" >/dev/null 2>&1 || true
}

deploy_site /opt/deploy/apps/divingclub-prod prod horizon-prod

# Staging (/opt/deploy/apps/divingclub) auto-deploy is suspended — re-enable here
# with its own supervisor program name when it goes back into service.
# deploy_site /opt/deploy/apps/divingclub staging horizon
