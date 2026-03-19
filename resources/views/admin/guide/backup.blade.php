@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>Automated Backups</h5>
<p>The system runs a weekly backup every Sunday at 03:00 via the <code>WeeklyBackup</code> job:</p>
<ul>
    <li>MySQL dump compressed with gzip</li>
    <li>Stored in <code>storage/app/backups/</code></li>
    <li>Last 4 backups retained, older ones auto-deleted</li>
</ul>

<h5>Manual Backup</h5>
<pre class="bg-light p-3 rounded"><code># Database dump
mysqldump -u divingclub -p divingclub | gzip > backup-$(date +%Y%m%d).sql.gz

# Full application backup (includes uploads)
tar czf divingclub-full-$(date +%Y%m%d).tar.gz \
    --exclude=node_modules --exclude=vendor \
    /path/to/divingclub</code></pre>

<h5>Scheduled Tasks</h5>
<p>The Laravel scheduler runs via cron (<code>* * * * * cd /path && php artisan schedule:run</code>):</p>
<table class="table table-sm">
    <thead><tr><th>Schedule</th><th>Task</th></tr></thead>
    <tbody>
        <tr><td>Daily 08:00</td><td>Medical certificate expiry reminders (30/15/7/0 days)</td></tr>
        <tr><td>Hourly</td><td>Auto-translate one untranslated article</td></tr>
        <tr><td>Every minute</td><td>Vote auto-open/close (checks opens_at/closes_at)</td></tr>
        <tr><td>1st of month 04:00</td><td>Audit log auto-purge (per retention policy)</td></tr>
        <tr><td>Sunday 03:00</td><td>Weekly database backup</td></tr>
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
