@extends('admin.guide.partials.guide-layout')
@section('content')
<p>After deploying DivingClub, follow these steps to get the system production-ready.</p>

<h5>1. Configure Environment Variables</h5>
<p>Edit <code>.env</code> on the server:</p>
<pre class="bg-light p-3 rounded"><code>APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.lu

# Mail — use real SMTP in production
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=noreply@divingclub.eu
MAIL_PASSWORD=your_mail_password
MAIL_ENCRYPTION=tls

# Club-specific
CLUB_IBAN=LU00 0000 0000 0000 0000
CLUB_ID=MYCLUB
FEDERATION_SALT=change_this_to_a_random_string</code></pre>

<h5>2. Set Up API Keys</h5>
<p>See <a href="{{ route('admin.guide.show', 'api-keys') }}">API Keys & OAuth Setup</a> for detailed instructions.</p>

<h5>3. Configure Federations</h5>
<p>Go to <a href="{{ route('admin.settings.index') }}">Settings → Federations</a>. 11 federations are pre-configured with 105 certification levels. Add or modify as needed.</p>

<h5>4. Set Up Medical Compliance Rules</h5>
<p>Go to Settings → Medical Compliance Rules. Six rules are pre-seeded for FFESSM and LIFRAS. Adjust validity periods and age brackets per your federation requirements.</p>

<h5>5. Configure Fee Components</h5>
<p>Go to <a href="{{ route('admin.payments.components') }}">Payments → Fee Components</a>. Set up a base fee and optional components (insurance, double affiliation).</p>
<p>Fee formula: <code>final = (base × status_multiplier × (1 − age_discount)) + optionals</code></p>
<ul>
    <li>Age &lt; 18 → 50% discount</li>
    <li>Age ≥ 65 → 25% discount</li>
    <li>Honorary status → always €0</li>
</ul>

<h5>6. Create the First Season</h5>
<p>Go to <a href="{{ route('admin.seasons.index') }}">Seasons</a> → Create with start/end dates, add weekly patterns, add holidays, then generate events.</p>

<h5>7. Set Up Equipment Maintenance Rules</h5>
<p>Settings → Equipment Maintenance Rules. Define intervals per equipment type (e.g., "Regulator service every 12 months").</p>

<h5>8. Customize Theme</h5>
<p>Settings → Theme & Appearance. Choose a preset (Ocean, Coral, Lagoon, Abyss, Tropical, Arctic) or set custom colors. Upload a logo.</p>

<h5>9. Verify Cron & Queue</h5>
<pre class="bg-light p-3 rounded"><code>crontab -l | grep artisan          # Verify scheduler cron
php artisan queue:work --daemon     # Start queue worker
php artisan schedule:list           # List scheduled tasks</code></pre>

<h5>10. Change Default Password</h5>
<p>Default admin: <code>admin@divingclub.eu</code> / <code>password</code>. <strong>Change immediately</strong> via profile page.</p>

<div class="alert alert-warning">
    <strong>Security:</strong> Ensure <code>APP_DEBUG=false</code> in production and change all default passwords.
</div>
@endsection
