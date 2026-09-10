#!/bin/bash
#
# Offsite copy of the prod backups + the bulky public media to Google Drive
# (rclone remote "gdrive", OAuth as clubcep@gmail.com — see docs/BACKUP.md).
#
# Canonical copy: repo deploy/gdrive-offsite.sh; runs as /opt/deploy/gdrive-offsite.sh
# on prod, from the clubcep crontab, once daily.
#
#   45 3 * * * /opt/deploy/gdrive-offsite.sh <kuma-push-token>
#
# Retention on the remote: the 2 newest backup zips (local keeps BACKUP_RETENTION).
# The pics mirror is current-state only (rclone sync), not versioned.
#
set -euo pipefail

RCLONE=(rclone --config /home/clubcep/.config/rclone/rclone.conf)
REMOTE="gdrive:DCMS/prod"
LOCAL_BACKUPS="/mnt/data/prod/backup/DivingClub"
LOCAL_PICS="/mnt/data/prod/pics/public"
KEEP=2
KUMA_TOKEN="${1:-}"
LOG=/var/log/gdrive-offsite.log

log() { echo "$(date '+%F %T') $*" | tee -a "$LOG"; }

fail() {
    log "FAILED: $*"
    [ -n "$KUMA_TOKEN" ] && curl -fsS -m10 "https://kuma.ecb.pm/api/push/${KUMA_TOKEN}?status=down&msg=$(printf '%s' "$1" | head -c 60 | tr ' ' '_')" >/dev/null 2>&1 || true
    exit 1
}
trap 'fail "unexpected error on line $LINENO"' ERR

# 1. push any new backup zips (rclone skips ones already uploaded)
log "copy backups → ${REMOTE}/backups"
"${RCLONE[@]}" copy "$LOCAL_BACKUPS" "${REMOTE}/backups" --include "backup-*.zip" \
    --transfers 2 --checkers 4 --drive-chunk-size 64M 2>>"$LOG"

# 2. keep only the $KEEP newest backup zips on the remote
mapfile -t OLD < <("${RCLONE[@]}" lsf "${REMOTE}/backups" --include "backup-*.zip" 2>>"$LOG" | sort -r | tail -n "+$((KEEP + 1))")
for f in "${OLD[@]:-}"; do
    [ -n "$f" ] || continue
    log "prune remote: $f"
    "${RCLONE[@]}" deletefile "${REMOTE}/backups/${f}" 2>>"$LOG"
done

# 3. mirror the bulky public media the backup zip deliberately excludes
log "sync pics → ${REMOTE}/pics-public"
"${RCLONE[@]}" sync "$LOCAL_PICS" "${REMOTE}/pics-public" \
    --fast-list --transfers 4 --checkers 8 --drive-chunk-size 64M 2>>"$LOG"

REMOTE_N=$("${RCLONE[@]}" lsf "${REMOTE}/backups" --include "backup-*.zip" 2>/dev/null | wc -l)
log "OK — ${REMOTE_N} backup zip(s) on Drive, pics mirrored"
trap - ERR
[ -n "$KUMA_TOKEN" ] && curl -fsS -m10 "https://kuma.ecb.pm/api/push/${KUMA_TOKEN}?status=up&msg=offsite-ok-${REMOTE_N}-zips" >/dev/null 2>&1 || true
