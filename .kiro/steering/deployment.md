# Deployment & Server Operations

## Cache Operations on Hetzner (test.clubcep.eu)

- PHP-FPM runs as user `clubcep` (pool `[clubcep]`, socket `/run/php/php8.3-fpm.sock`)
- After deleting or clearing cache files on the server, **always** fix ownership:
  ```bash
  ssh root@204.168.168.60 "chown -R clubcep:clubcep /opt/deploy/apps/divingclub/bootstrap/cache/ /opt/deploy/apps/divingclub/storage/"
  ```
- If running artisan commands as `root`, the regenerated cache files will be owned by root and PHP-FPM (running as `clubcep`) will get "Permission denied" → HTTP 500 with empty response body.
- Prefer running artisan commands as the app user:
  ```bash
  ssh root@204.168.168.60 "sudo -u clubcep php /opt/deploy/apps/divingclub/artisan view:clear"
  ```

## Deploy Flow (No Git Deploy Key)

The GitHub deploy key is not configured on the server (see TODO §7.9). Deploy via scp:

```bash
# Single file
scp path/to/file root@204.168.168.60:/opt/deploy/apps/divingclub/path/to/file

# After deploying, clear caches as the app user
ssh root@204.168.168.60 "sudo -u clubcep php /opt/deploy/apps/divingclub/artisan view:clear && sudo -u clubcep php /opt/deploy/apps/divingclub/artisan optimize:clear"
```

## Server Details

| Item | Value |
|------|-------|
| IP | 204.168.168.60 |
| SSH | `ssh root@204.168.168.60` |
| App path | `/opt/deploy/apps/divingclub` |
| App user | `clubcep` |
| PHP-FPM pool | `/etc/php/8.3/fpm/pool.d/clubcep.conf` |
| Caddy config | `/etc/caddy/Caddyfile` |
| PHP-FPM socket | `/run/php/php8.3-fpm.sock` |
| Logs | `/var/log/caddy/access.log`, `/var/log/php8.3-fpm.log` |
| Laravel log | `storage/logs/laravel.log` |
| APP_ENV | staging |
| APP_DEBUG | true |

## Known Issues

- `barryvdh/laravel-debugbar` is in `require-dev` but not installed on server (`--no-dev`). Running `php artisan optimize:clear` as root previously triggered "Class not found" — this is fixed by always running artisan as `clubcep`.
- Disk usage is at 90% — monitor and clean old backups/logs if needed.
