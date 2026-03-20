@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>Common Issues</h5>

<div class="accordion" id="troubleAccordion">
    @foreach([
        'Pages show 500 error' => '<ol><li>Check <code>storage/logs/laravel.log</code> for the actual error</li><li>Ensure <code>APP_KEY</code> is set: <code>php artisan key:generate</code></li><li>Check permissions: <code>sudo chown -R www-data:www-data storage bootstrap/cache && sudo chmod -R 775 storage bootstrap/cache</code></li></ol>',
        'CSS/JS not loading' => '<ol><li>Run <code>npm run build</code> to compile assets</li><li>Check that <code>public/build/manifest.json</code> exists</li><li>Ensure Nginx serves the <code>public/</code> directory</li></ol>',
        'Emails not sending' => '<ol><li>Check <code>MAIL_MAILER</code> in <code>.env</code> (use <code>log</code> for testing)</li><li>Ensure queue worker is running: <code>php artisan queue:work</code></li><li>Check <code>failed_jobs</code> table: <code>php artisan queue:failed</code></li></ol>',
        'OAuth login fails' => '<ol><li>Verify Client ID and Secret in <code>.env</code></li><li>Check callback URL matches exactly (including https vs http)</li><li>Ensure the OAuth app is published/approved at the provider</li></ol>',
        'Medical compliance shows wrong status' => '<ol><li>Check rules in Settings → Medical Compliance Rules</li><li>Verify the member\'s federation and date of birth are set</li><li>Re-upload the medical certificate to trigger re-evaluation</li></ol>',
        'Events not generating from season' => '<ol><li>Ensure season has patterns defined</li><li>Check that holidays don\'t cover all dates</li><li>Day numbering: 0=Monday, 6=Sunday</li></ol>',
        'Bank reconciliation not matching' => '<ol><li>Communication string must match the format: <code>CLUB-YEAR-ID-NAME</code> (where CLUB is your Short Code from Settings)</li><li>Suggestion threshold is 60/100 — partial matches may not appear</li><li>Use manual confirmation for edge cases</li></ol>',
        'Theme changes not visible' => '<ol><li>Theme is cached for 5 minutes. Clear cache: <code>php artisan cache:clear</code></li><li>Hard-refresh browser: Ctrl+Shift+R</li></ol>',
        'Language not switching' => '<ol><li>Click the language dropdown in the header</li><li>For authenticated users, the preference is saved to their profile</li><li>For guests, it\'s stored in the session</li><li>Check that <code>lang/{locale}/messages.php</code> exists</li></ol>',
    ] as $q => $a)
        <div class="accordion-item">
            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#t{{ $loop->index }}">{{ $q }}</button></h2>
            <div id="t{{ $loop->index }}" class="accordion-collapse collapse" data-bs-parent="#troubleAccordion">
                <div class="accordion-body">{!! $a !!}</div>
            </div>
        </div>
    @endforeach
</div>

<h5 class="mt-4">Useful Commands</h5>
<pre class="bg-light p-3 rounded"><code># Check application health
php artisan about

# List all routes
php artisan route:list

# Database state
php artisan tinker --execute="echo App\Models\User::count().' users'"

# Clear everything
php artisan optimize:clear

# Re-run migrations (DESTRUCTIVE)
php artisan migrate:fresh --seed

# Check scheduler
php artisan schedule:list

# Process failed jobs
php artisan queue:retry all</code></pre>

<h5>Getting Help</h5>
<ul>
    <li>Laravel docs: <a href="https://laravel.com/docs" target="_blank">laravel.com/docs</a></li>
    <li>Application source: check <code>app/</code> directory structure</li>
    <li>Database schema: check <code>database/migrations/</code></li>
</ul>
@endsection
