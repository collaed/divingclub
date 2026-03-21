@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>Audit Log</h5>
<p>Every data modification in the system is automatically logged via the <code>Auditable</code> trait. Go to Administration → Audit Log.</p>

<h5>Browsing & Filtering</h5>
<ul>
    <li><strong>User ID</strong> — filter by who made the change</li>
    <li><strong>Action</strong> — created, updated, deleted, sso_linked, impersonate_start</li>
    <li><strong>Model type</strong> — e.g. "User", "Event", "Document"</li>
    <li><strong>Date range</strong> — from/to date pickers</li>
</ul>
<p>The summary column shows which fields were changed (for updates).</p>

<h5>Detail View</h5>
<p>Click <strong>View</strong> on any entry to see the full diff:</p>
<ul>
    <li><strong>Updated</strong> — side-by-side before/after table with color coding</li>
    <li><strong>Created</strong> — all initial values</li>
    <li><strong>Deleted</strong> — all values at time of deletion</li>
    <li>IP address, user agent, and impersonation warnings are shown</li>
</ul>

<h5>CSV Export</h5>
<p>Click the @icon('📥') button to export the current filtered view as CSV. Useful for bureau meetings or compliance audits.</p>

<h5>Retention Policy</h5>
<p>Set the auto-purge period (6–60 months) in the audit log header. The system automatically deletes entries older than this threshold on the 1st of each month at 04:00.</p>
<p>You can also manually purge entries older than 1–5 years using the Purge button.</p>

<h5>What's Logged</h5>
<p>Any model using the <code>Auditable</code> trait: User, MemberDetail, Event, EventRegistration, Document, Equipment, and more. Each log entry records: old values, new values, user ID, IP address, user agent, and timestamp.</p>
@endsection
