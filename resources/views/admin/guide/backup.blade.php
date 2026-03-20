@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>Backup System</h5>
<p>The backup system creates compressed archives containing the full database and all uploaded files (avatars, medical certificates, documents, article images, event photos).</p>

<h5>Admin Interface</h5>
<p>Go to <strong>Admin → 💾 Backups</strong> (<code>/admin/backups</code>) to:</p>
<ul>
    <li><strong>Create a backup</strong> — choose DB-only or DB + files. The archive includes a <code>manifest.json</code> with table row counts, file inventory, and system info.</li>
    <li><strong>Inspect a backup</strong> — click any backup to see its manifest: table-by-table row counts, storage files grouped by folder (public and 🔒 private), PHP/Laravel versions.</li>
    <li><strong>Download</strong> — download any <code>.tar.gz</code> archive directly.</li>
    <li><strong>Delete</strong> — remove old backups manually.</li>
</ul>

<h5>What's Inside a Backup</h5>
<table class="table table-sm">
    <thead><tr><th>File</th><th>Contents</th></tr></thead>
    <tbody>
        <tr><td><code>manifest.json</code></td><td>Timestamp, DB driver, table row counts, file counts, app/PHP/Laravel versions</td></tr>
        <tr><td><code>database.sql.gz</code></td><td>MySQL dump (compressed) — or <code>database.sqlite</code> for SQLite</td></tr>
        <tr><td><code>public/</code></td><td>Avatars, article images, dive site photos, event photos, stock images</td></tr>
        <tr><td><code>private/</code></td><td>Medical certificates, scanned cards, document library files</td></tr>
    </tbody>
</table>

<h5>Database Support</h5>
<ul>
    <li><strong>MySQL</strong> — uses <code>mysqldump</code> piped through gzip</li>
    <li><strong>SQLite</strong> — copies the <code>.sqlite</code> file directly (no external tools needed)</li>
</ul>

<h5>Automated Backups</h5>
<p>The <code>WeeklyBackup</code> job runs every Sunday at 03:00 via the Laravel scheduler:</p>
<ul>
    <li>Creates a full backup (DB + all files)</li>
    <li>Retains the last {{ config('backup.retention', 4) }} backups, older ones auto-deleted</li>
    <li>Stored in <code>storage/app/backups/</code></li>
    <li>Configure retention via <code>BACKUP_RETENTION</code> in <code>.env</code> (default: 4)</li>
</ul>

<h5>Restoring a Backup</h5>
<pre class="bg-light p-3 rounded"><code># Extract the archive
tar xzf backup-2026-03-20-130000.tar.gz

# Restore database (MySQL)
gunzip -c database.sql.gz | mysql -u user -p divingclub

# Restore database (SQLite)
cp database.sqlite storage/app/database.sqlite

# Restore files
cp -r public/* storage/app/public/
cp -r private/* storage/app/private/</code></pre>

<h5>Scheduled Tasks</h5>
<p>The Laravel scheduler runs via cron (<code>* * * * * cd /path && php artisan schedule:run</code>):</p>
<table class="table table-sm">
    <thead><tr><th>Schedule</th><th>Task</th></tr></thead>
    <tbody>
        <tr><td>Daily 08:00</td><td>Medical certificate expiry reminders (30/15/7/0 days)</td></tr>
        <tr><td>Hourly</td><td>Auto-translate one untranslated article</td></tr>
        <tr><td>Every minute</td><td>Vote auto-open/close (checks opens_at/closes_at)</td></tr>
        <tr><td>1st of month 04:00</td><td>Audit log auto-purge (per retention policy)</td></tr>
        <tr><td>Sunday 03:00</td><td>Weekly full backup (DB + files)</td></tr>
    </tbody>
</table>

<h5>Queue Worker</h5>
<p>The queue driver is <code>database</code>. Start the worker:</p>
<pre class="bg-light p-3 rounded"><code># Development
php artisan queue:work

# Production (use supervisor)
sudo apt install supervisor
sudo nano /etc/supervisor/conf.d/divingclub-worker.conf</code></pre>
<p>Supervisor config:</p>
<pre class="bg-light p-3 rounded"><code>[program:divingclub-worker]
command=php /path/to/divingclub/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/divingclub-worker.log</code></pre>

<h5>Log Rotation</h5>
<p>Laravel logs to <code>storage/logs/laravel.log</code>. Configure log rotation in <code>config/logging.php</code> (default: daily, 14 days retention).</p>

<h5>Cache Management</h5>
<pre class="bg-light p-3 rounded"><code>php artisan config:clear    # Clear config cache
php artisan cache:clear     # Clear application cache
php artisan view:clear      # Clear compiled views
php artisan route:clear     # Clear route cache

# Production optimization
php artisan config:cache
php artisan route:cache
php artisan view:cache</code></pre>
@endsection
