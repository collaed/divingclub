@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>Seasons</h5>
<p>A season represents a club year (e.g., September 2025 – June 2026). Go to Administration → Seasons.</p>
<ol>
    <li><strong>Create</strong> a season with name, year, start/end dates</li>
    <li><strong>Add holidays</strong> — dates when no events should be generated (Christmas break, school holidays, etc.)</li>
    <li><strong>Add weekly patterns</strong> — recurring events (e.g., "Pool Training every Wednesday 19:00–21:00 at Bonnevoie")</li>
    <li><strong>Preview</strong> — see all dates that would be generated</li>
    <li><strong>Generate</strong> — creates individual events from patterns, skipping holidays</li>
</ol>
<p>Day numbering: 0=Monday, 1=Tuesday, ..., 6=Sunday.</p>

<h5>Events</h5>
<p>Events appear on the Calendar (month/week/day views). Types with default colors:</p>
<table class="table table-sm">
    <thead><tr><th>Type</th><th>Color</th><th>Use</th></tr></thead>
    <tbody>
        <tr><td>pool</td><td><span class="badge" style="background:#0077be">Blue</span></td><td>Pool training sessions</td></tr>
        <tr><td>dive</td><td><span class="badge" style="background:#003366">Navy</span></td><td>Open water dives</td></tr>
        <tr><td>training</td><td><span class="badge" style="background:#28a745">Green</span></td><td>Theory/practical training</td></tr>
        <tr><td>theory</td><td><span class="badge" style="background:#6f42c1">Purple</span></td><td>Classroom sessions</td></tr>
        <tr><td>social</td><td><span class="badge" style="background:#ffc107;color:#333">Yellow</span></td><td>Social events, parties</td></tr>
    </tbody>
</table>

<h5>Registration & Waiting List</h5>
<ul>
    <li>Set <code>max_participants</code> to enable capacity limits</li>
    <li>Enable <code>waiting_list</code> — when full, new registrations go to waiting list</li>
    <li>When someone cancels, the first person on the waiting list is auto-promoted</li>
    <li>Medical compliance is checked at registration time for pool/dive/training events</li>
</ul>

<h5>WhatsApp Groups</h5>
<p>Each event (and season pattern) can have a WhatsApp group URL. To get the link:</p>
<ol>
    <li>Open WhatsApp → open the group</li>
    <li>Group Info → Invite via link → Copy link</li>
    <li>Paste the <code>https://chat.whatsapp.com/...</code> URL in the event form</li>
</ol>
<p>When set on a season pattern, all generated events inherit the WhatsApp link. The link appears as a green "Join WhatsApp Group" button on the event page.</p>

<h5>Deposits</h5>
<p>Events can have up to 3 deposit instalments with dates and amounts. These generate payment_expected records for each registered participant.</p>
@endsection
