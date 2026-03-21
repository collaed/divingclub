@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>Minors & Parental Consent</h5>
<p>Go to Administration → Minors & Consent. This page lists all members under 18 (based on date of birth).</p>

<h5>Linking Guardians</h5>
<p>Each minor needs at least one guardian linked. Expand a minor's row and select a member as their parent or legal guardian. The guardian must also be a registered member.</p>
<p>The dashboard worklist shows a red alert for any minor without a linked guardian.</p>

<h5>Recording Consent</h5>
<p>Four consent types are tracked per minor:</p>
<ul>
    <li><strong>General</strong> — overall membership consent</li>
    <li><strong>Events</strong> — participation in club activities</li>
    <li><strong>Photos</strong> — photo publication on website/social media</li>
    <li><strong>Medical</strong> — club managing medical certificates</li>
</ul>
<p>Each consent can include an uploaded document (signed authorization form, PDF or image). Documents are stored privately and accessible only to bureau members.</p>

<h5>Revoking Consent</h5>
<p>Click Revoke next to any active consent. The revocation is timestamped and audit-logged. The consent badge changes from green @icon('✓') to yellow ✗.</p>

<h5>Age Calculation</h5>
<p>The system uses the member's <code>date_of_birth</code> field. When a minor turns 18, they no longer appear on this page and manage their own consents via the Privacy page.</p>
@endsection
