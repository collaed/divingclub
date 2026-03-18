@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>GDPR Features</h5>
<p>The system provides three GDPR compliance features accessible to all members via the Privacy menu:</p>

<h5>1. Consent Management</h5>
<p>Members can grant or revoke consent for:</p>
<ul>
    <li><strong>Data Processing</strong> — required for membership</li>
    <li><strong>Marketing</strong> — newsletter and promotional emails</li>
    <li><strong>Photo Publication</strong> — photos on website/social media</li>
</ul>
<p>Each consent change is timestamped. The email system respects marketing consent when targeting groups.</p>

<h5>2. Data Export</h5>
<p>Members can download a complete JSON export of all their personal data: profile, emails, licences, documents metadata, and consent history.</p>

<h5>3. Right to Erasure</h5>
<p>Members can request account erasure. This:</p>
<ul>
    <li>Deletes all uploaded documents and avatar from storage</li>
    <li>Anonymizes all personal fields (name → "ERASED", email → "erased-ID@erased.local")</li>
    <li>Deletes all email addresses and social account links</li>
    <li>Logs the erasure in the audit log</li>
    <li>Logs the user out</li>
</ul>
<p>The user record is kept (anonymized) to maintain referential integrity in audit logs and event history.</p>

<h5>Cookie Consent</h5>
<p>Unauthenticated visitors see a cookie consent banner. Accepting sets a 1-year cookie. The banner is shown via JavaScript — no tracking occurs before consent.</p>

<h5>Audit Trail</h5>
<p>All data modifications are logged via the Auditable trait. The audit log (Administration → Audit Log) shows who changed what, when, with old and new values.</p>
@endsection
