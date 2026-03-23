@extends('admin.guide.partials.guide-layout')
@section('content')
<p>Welcome to the DivingClub administration system — the management platform for your diving club.</p>

<h5>What This System Manages</h5>
<div class="row g-2 mb-3">
    @foreach([
        '👥 Members' => 'Profiles, roles, statuses, multi-federation licences, certification levels, guardian/minor management',
        '📅 Events' => 'Calendar, recurring seasons, registration, waiting lists, WhatsApp groups, event photos',
        '🫧 Dive Groups' => 'Palanquée planner, 39 rules across 5 federations, mixed-level validation',
        '🏥 Medical' => 'Certificate tracking per federation, automated reminders, compliance gate',
        '💰 Payments' => 'Fee calculation, bank reconciliation with IBAN matching, SEPA QR codes',
        '🤿 Equipment' => 'Inventory, loans, maintenance scheduling',
        '📰 Content' => 'Typed articles, auto-translation, image galleries, comments, classifieds, document library',
        '✉️ Email' => 'Templates, group targeting, bilingual, send log',
        '🗳️ Voting' => 'Simple polls and anonymous elections, multi-select, public results',
        '🤝 Partnerships' => 'Inter-club federation API, symmetric key exchange, cross-registration',
        '📱 Social Media' => 'Facebook auto-publish with triple GDPR gate',
        '🔒 GDPR' => 'Consent management, parental consent for minors, data export, erasure',
        '📋 Audit Log' => 'Full change history, diff view, CSV export, retention policy',
        '📊 Dashboard' => 'Statistics, bureau worklist, CSV exports',
        '💾 Backups' => 'Admin UI: create/inspect/download/delete, DB + files archive, MySQL & SQLite, weekly auto-backup',
    ] as $label => $desc)
        <div class="col-md-6"><div class="border rounded p-2"><strong>{{ $label }}</strong><br><small class="text-muted">{{ $desc }}</small></div></div>
    @endforeach
</div>

<h5>Roles</h5>
<table class="table table-sm">
    <thead><tr><th>Role</th><th>Access</th></tr></thead>
    <tbody>
        <tr><td><code>bureau_master</code></td><td>Full admin — all settings, members, finances, equipment, communications</td></tr>
        <tr><td><code>bureau_member</code></td><td>Bureau-level view access</td></tr>
        <tr><td><code>instructor</code></td><td>Manage events they instruct, view participants</td></tr>
        <tr><td><code>assistant</code></td><td>Assist on events</td></tr>
        <tr><td><code>member</code></td><td>Standard — profile, events, documents</td></tr>
        <tr><td><code>pending</code></td><td>Awaiting approval after registration</td></tr>
    </tbody>
</table>

<h5>Member Statuses (French slugs)</h5>
<table class="table table-sm">
    <thead><tr><th>Status</th><th>Slug</th><th>Fee ×</th><th>Notes</th></tr></thead>
    <tbody>
        <tr><td>Actif</td><td><code>actif</code></td><td>1.00</td><td>Standard active member</td></tr>
        <tr><td>Fonctionnaire</td><td><code>fonctionnaire</code></td><td>1.00</td><td>Civil servant</td></tr>
        <tr><td>Honoraire</td><td><code>honoraire</code></td><td>0.00</td><td>Honorary — always free</td></tr>
        <tr><td>Junior</td><td><code>junior</code></td><td>0.50</td><td>Under-18</td></tr>
        <tr><td>Famille</td><td><code>famille</code></td><td>0.75</td><td>Family membership</td></tr>
        <tr><td>Membre de droit</td><td><code>membre_de_droit</code></td><td>0.00</td><td>Member by right</td></tr>
    </tbody>
</table>

<h5>Supported Federations</h5>
<p>{{ \App\Models\Federation::count() }} federations configured with {{ \App\Models\CertificationLevel::count() }} certification levels across FFESSM, LIFRAS, FLASSA, NELOS, VDST, PADI, SSI, UCPA, BSAC, NASDS, and CMAS.</p>

<div class="alert alert-info">
    <strong>Next:</strong> Read <a href="{{ route('admin.guide.show', 'first-steps') }}">First Steps After Deployment</a> to configure the system.
</div>

<h5>Screenshots</h5>
<div class="row g-2">
    @foreach(['home' => 'Home Page', 'admin-dashboard' => 'Dashboard', 'calendar' => 'Calendar', 'profile' => 'Member Profile', 'admin-members' => 'Members List', 'admin-settings' => 'Settings'] as $img => $caption)
        <div class="col-md-4">
            <figure class="figure">
                <img src="/images/guide/{{ $img }}.png" class="figure-img img-fluid rounded border" alt="{{ $caption }}">
                <figcaption class="figure-caption text-center">{{ $caption }}</figcaption>
            </figure>
        </div>
    @endforeach
</div>
@endsection
