@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>Email Templates</h5>
<p>Go to Administration → Email. Create reusable templates with variable placeholders:</p>
<table class="table table-sm">
    <thead><tr><th>Variable</th><th>Replaced With</th></tr></thead>
    <tbody>
        <tr><td><code>@{{first_name}}</code></td><td>Member's first name</td></tr>
        <tr><td><code>@{{last_name}}</code></td><td>Member's last name</td></tr>
        <tr><td><code>@{{name}}</code></td><td>Full name</td></tr>
        <tr><td><code>@{{email}}</code></td><td>Primary email</td></tr>
        <tr><td><code>@{{club_name}}</code></td><td>Club name from theme settings</td></tr>
    </tbody>
</table>

<h5>Group Targeting</h5>
<p>Select a recipient group when sending:</p>
<ul>
    <li><strong>All members</strong> — everyone in the system</li>
    <li><strong>Active members</strong> — status = actif</li>
    <li><strong>Instructors</strong> — role = instructor</li>
    <li><strong>Bureau</strong> — bureau_master + bureau_member roles</li>
    <li><strong>Expiring certificates</strong> — medical cert expiring within 30 days</li>
    <li><strong>Unpaid memberships</strong> — pending payment_expected records</li>
</ul>

<h5>Sending</h5>
<ol>
    <li>Select a template (or write a custom subject/body)</li>
    <li>Choose the target group</li>
    <li>Click "Preview" to see the rendered email with sample data</li>
    <li>Click "Send" — emails are queued and dispatched (3 retry attempts)</li>
</ol>

<h5>Email Log</h5>
<p>All sent emails are logged with: recipient, subject, status (queued/sent/failed), error message, timestamp.</p>

<h5>Mail Configuration</h5>
<p>In production, configure SMTP in <code>.env</code>. For development, <code>MAIL_MAILER=log</code> writes emails to <code>storage/logs/laravel.log</code>.</p>
<p>Recommended providers: Mailgun, Amazon SES, Postmark, or your hosting provider's SMTP.</p>
@endsection
