@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>How It Works</h5>
<p>Medical compliance is evaluated per federation rules. Each rule defines: federation, age bracket, certificate type, and validity in months.</p>

<h5>Certificate Types</h5>
<table class="table table-sm">
    <thead><tr><th>Type</th><th>Description</th></tr></thead>
    <tbody>
        <tr><td><code>gp</code></td><td>General Practitioner certificate</td></tr>
        <tr><td><code>ent</code></td><td>Ear, Nose & Throat specialist</td></tr>
        <tr><td><code>cardio</code></td><td>Cardiologist certificate</td></tr>
        <tr><td><code>ophthalmologist</code></td><td>Eye specialist</td></tr>
        <tr><td><code>other</code></td><td>Other medical certificate</td></tr>
    </tbody>
</table>

<h5>Compliance Evaluation</h5>
<p>When a member uploads a medical certificate:</p>
<ol>
    <li>System finds all rules matching the member's federation(s) and age</li>
    <li>Uses the <strong>most restrictive</strong> validity period</li>
    <li>Calculates expiry date from certificate issue date</li>
    <li>Sets compliance status: compliant / expiring soon (30 days) / expired</li>
</ol>

<h5>Compliance Badges</h5>
<ul>
    <li><span class="badge bg-success">Compliant</span> — valid certificate on file</li>
    <li><span class="badge bg-warning text-dark">Expiring</span> — expires within 30 days</li>
    <li><span class="badge bg-danger">Non-compliant</span> — expired or no certificate</li>
</ul>
<p>Badges appear on: profile header, medical tab, admin members list, event participant list.</p>

<h5>Event Gate</h5>
<p>Members with non-compliant medical status cannot register for pool, dive, or training events. Social events are exempt.</p>

<h5>Automated Reminders</h5>
<p>Daily at 08:00, the system sends email reminders at 30, 15, 7, and 0 days before certificate expiry. Each reminder is sent only once (tracked via <code>reminder_*_sent_at</code> columns).</p>

<h5>Certificate Verification</h5>
<p>Bureau Master can verify uploaded certificates by clicking "Verify" on the medical tab. This marks the document as verified in the audit log.</p>

<h5>Managing Rules</h5>
<p>Go to Settings → Medical Compliance Rules to add/edit/delete rules. Pre-seeded rules:</p>
<ul>
    <li>FFESSM: GP 12 months (all ages), ENT 12 months (40+)</li>
    <li>LIFRAS: GP 12 months (&lt;40), GP 6 months (40+), Cardio 24 months (50+)</li>
</ul>
@endsection
