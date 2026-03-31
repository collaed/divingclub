@extends('admin.guide.partials.guide-layout')
@section('guide-title', __('Inbound Mail Aliases'))
@section('guide-content')
<h5>📬 {{ __('Mail Alias System') }}</h5>
<p>Send an email to a special address and it gets forwarded to the right group of members.</p>

<table class="table table-sm">
    <thead><tr><th>{{ __('Address') }}</th><th>{{ __('Recipients') }}</th><th>{{ __('Who can send') }}</th></tr></thead>
    <tbody>
        @foreach(\App\Services\MailAliasService::staticAliases() as $alias => $desc)
            <tr><td><code>{{ $alias }}@{{ config('club.domain') }}</code></td><td>{{ $desc }}</td><td>Bureau</td></tr>
        @endforeach
    </tbody>
</table>

<h5 class="mt-4">📝 {{ __('Subject Directives') }}</h5>
<p>Add <code>(recipients: ...)</code> in the email subject to target specific groups:</p>
<ul>
    <li><code>(recipients: bureau)</code> — bureau members</li>
    <li><code>(recipients: moniteurs)</code> — instructors</li>
    <li><code>(recipients: sortie=42)</code> — participants of event #42</li>
    <li><code>(recipients: bureau, moniteurs, sortie=42)</code> — combined</li>
    <li><code>(recipients: Michel B, Jean-Claude H)</code> — by name</li>
    <li><code>(recipients: bureau, simulate)</code> — dry run (sends report, no forwarding)</li>
</ul>
<p>The directive is removed from the subject before forwarding.</p>

<h5 class="mt-4">⚙️ {{ __('Setup Options') }}</h5>

<div class="card dc-card mb-3">
    <div class="card-header">{{ __('Option A: IMAP Polling (recommended)') }}</div>
    <div class="card-body">
        <p>The app checks a mailbox every minute for new messages. No server config needed.</p>
        <p>Add to <code>.env</code>:</p>
        <pre class="bg-light p-2 rounded">INBOUND_MAIL_ENABLED=true
INBOUND_IMAP_HOST=mail.ecb.pm
INBOUND_IMAP_PORT=993
INBOUND_IMAP_USER=members@ecb.pm
INBOUND_IMAP_PASSWORD=your_password
INBOUND_IMAP_ENCRYPTION=ssl</pre>
        <p>Create the mailbox: <code>docker exec mailserver setup email add members@ecb.pm password</code></p>
        <p>Then forward/redirect <code>members@clubcep.eu</code> to <code>members@ecb.pm</code> in your hosting panel.</p>
    </div>
</div>

<div class="card dc-card">
    <div class="card-header">{{ __('Option B: Postfix Pipe (instant, advanced)') }}</div>
    <div class="card-body">
        <p>Postfix delivers directly to the artisan command. Zero delay but requires server access.</p>
        <p>On the mail server, add to <code>/tmp/docker-mailserver/postfix-main.cf</code>:</p>
        <pre class="bg-light p-2 rounded">virtual_alias_maps = regexp:/etc/postfix/virtual_alias</pre>
        <p>Create <code>/tmp/docker-mailserver/postfix-virtual.cf</code>:</p>
        <pre class="bg-light p-2 rounded">/^(bureau|members|instructors|event-\d+|members\.\w+)@/ pipe:flags=F user=deploy argv=/usr/bin/php /opt/deploy/apps/divingclub/artisan mail:inbound --to=${recipient} --from=${sender}</pre>
        <p>This routes matching addresses to the Laravel command instantly.</p>
    </div>
</div>
@endsection
