@extends('admin.guide.partials.guide-layout')
@section('guide-title', __('System & Monitoring'))
@section('guide-content')
<h5>⏱️ {{ __('Scheduled Tasks') }}</h5>
<p>The dashboard shows a <strong>Scheduled Tasks</strong> table at the bottom with the status of each automated job:</p>
<table class="table table-sm">
    <thead><tr><th>Task</th><th>Schedule</th><th>What it does</th></tr></thead>
    <tbody>
        <tr><td><code>medical-reminders</code></td><td>Daily 08:00</td><td>Sends expiry reminders at 30/15/7/0 days</td></tr>
        <tr><td><code>weekly-backup</code></td><td>Sunday 03:00</td><td>Full DB + files backup, prunes old ones</td></tr>
        <tr><td><code>translations</code></td><td>Hourly</td><td>Translates new articles, refreshes stale ones</td></tr>
        <tr><td><code>vote-auto</code></td><td>Every minute</td><td>Opens/closes votes at scheduled times</td></tr>
        <tr><td><code>audit-purge</code></td><td>Monthly</td><td>Deletes old audit logs per retention policy</td></tr>
        <tr><td><code>classifieds-cleanup</code></td><td>Monthly</td><td>Unpublishes expired ads, deletes after 3 months</td></tr>
        <tr><td><code>equipment-reminders</code></td><td>Daily 09:00</td><td>Notifies overdue equipment loans</td></tr>
    </tbody>
</table>
<p>Status badges: 🟢 OK, 🔴 Failed, 🟡 Overdue (>25h), ⚪ Never run.</p>

<h5 class="mt-4">⏱️ {{ __('Queue Monitor (Horizon)') }}</h5>
<p>Access via <strong>Admin → Queue Monitor</strong>. Shows real-time job processing, failed jobs, throughput, and wait times. Only bureau members can access it.</p>

<h5 class="mt-4">🔄 {{ __('System Updates') }}</h5>
<p>The dashboard shows the current version and git commit. Click <strong>🔍 Check</strong> to query GitHub for new releases.</p>
<p>If an update is available, click <strong>⬆️ Update Now</strong> to:</p>
<ol>
    <li>Pull latest code from GitHub</li>
    <li>Run <code>composer install</code></li>
    <li>Run database migrations</li>
    <li>Clear all caches</li>
    <li>Restart queue workers</li>
</ol>
<p class="text-danger"><strong>⚠️ Only Bureau Master can apply updates.</strong> Always check the release notes before updating.</p>

<h5 class="mt-4">🔐 {{ __('Permissions') }}</h5>
<p>The system uses <strong>spatie/laravel-permission</strong> for role-based access. Current permissions:</p>
<ul>
    @foreach(\Spatie\Permission\Models\Permission::orderBy('name')->get() as $p)
        <li><code>{{ $p->name }}</code></li>
    @endforeach
</ul>
<p>Bureau Master has all permissions. Bureau Finance/Technical have a subset. Instructors can manage events and dive sites.</p>
@endsection
