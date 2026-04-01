@extends('admin.guide.partials.guide-layout')
@section('guide-title', __('Inbound Mail Aliases'))
@section('guide-content')
<h5>📬 {{ __('Mail Alias System') }}</h5>
<p>Send an email to a special address and it gets forwarded to the right group of members. Uses standard <strong>plus-addressing</strong> (RFC 5233) — works on any mail server with zero configuration.</p>

<h5 class="mt-4">📧 {{ __('How it works') }}</h5>
<p>Your club has one base address configured in <code>.env</code>:</p>
<pre class="bg-light p-2 rounded">CLUB_MAIL_ADDRESS={{ config('club.mail_address', 'clubcep@test.clubcep.eu') }}</pre>
<p>Add a <code>+tag</code> to target specific groups:</p>

<table class="table table-sm">
    <thead><tr><th>{{ __('Address') }}</th><th>{{ __('Recipients') }}</th></tr></thead>
    <tbody>
        @foreach(\App\Services\MailAliasService::staticAliases() as $tag => $desc)
            <tr><td><code>{{ \App\Services\MailAliasService::mailtoAddress($tag) }}</code></td><td>{{ $desc }}</td></tr>
        @endforeach
    </tbody>
</table>

<h5 class="mt-4">📝 {{ __('Subject Directives') }}</h5>
<p>Add <code>(recipients: ...)</code> in the email subject to target multiple groups at once:</p>
<ul>
    <li><code>(recipients: bureau)</code> — bureau members</li>
    <li><code>(recipients: instructors)</code> — active instructors</li>
    <li><code>(recipients: event.42)</code> — participants of event #42</li>
    <li><code>(recipients: bureau, instructors, event.42)</code> — combined</li>
    <li><code>(recipients: Michel B, Jean-Claude H)</code> — by name</li>
    <li><code>(recipients: bureau, simulate)</code> — dry run (sends report, no forwarding)</li>
</ul>

<h5 class="mt-4">⚙️ {{ __('Setup') }}</h5>

<div class="card dc-card mb-3">
    <div class="card-header">{{ __('Step 1: Create a dedicated user (one-time, needs sudo)') }}</div>
    <div class="card-body">
        <pre class="bg-light p-2 rounded">sudo useradd -m -s /usr/sbin/nologin -c "My Diving Club" clubname</pre>
        <p>The app should run as this user. Postfix delivers mail to this user's Maildir automatically via plus-addressing — <strong>no Postfix configuration needed</strong>.</p>
    </div>
</div>

<div class="card dc-card mb-3">
    <div class="card-header">{{ __('Step 2: Configure .env') }}</div>
    <div class="card-body">
        <pre class="bg-light p-2 rounded">CLUB_MAIL_ADDRESS=clubname@yourdomain.com
INBOUND_MAIL_ENABLED=true
INBOUND_MAIL_MODE=maildir
INBOUND_MAILDIR=/home/clubname/Maildir</pre>
        <p>That's it. The app watches <code>Maildir/new/</code> every minute and processes incoming messages.</p>
    </div>
</div>

<div class="card dc-card mb-3">
    <div class="card-header">{{ __('Alternative: IMAP mode (mail on a different server)') }}</div>
    <div class="card-body">
        <p>If your mail is hosted elsewhere (Gmail, shared hosting, etc.), use IMAP mode instead:</p>
        <pre class="bg-light p-2 rounded">INBOUND_MAIL_MODE=imap
INBOUND_IMAP_HOST=imap.yourprovider.com
INBOUND_IMAP_PORT=993
INBOUND_IMAP_USER=clubname@yourdomain.com
INBOUND_IMAP_PASSWORD=your_password
INBOUND_IMAP_ENCRYPTION=ssl</pre>
    </div>
</div>

<h5 class="mt-4">🔒 {{ __('Security') }}</h5>
<ul>
    <li>Only <strong>bureau members and instructors</strong> can send to aliases</li>
    <li>Unknown senders are rejected and logged</li>
    <li>All forwarded messages are recorded in the <strong>Communications</strong> section of the event page</li>
    <li>The app user needs <strong>no sudo, no root, no special privileges</strong></li>
</ul>
@endsection
