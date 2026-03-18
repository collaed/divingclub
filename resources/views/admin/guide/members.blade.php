@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>Member List</h5>
<img src="/images/guide/admin-members.png" class="img-fluid rounded border mb-3" alt="Members List" style="max-height:300px">
<p>Go to Administration → Members to see all registered users. The list shows avatar, name, email, role, status, and medical compliance badge.</p>

<h5>Editing a Member</h5>
<p>Click a member name to view/edit their full profile. As Bureau Master you can edit all tabs:</p>
<ul>
    <li><strong>Info</strong> — name, nationality, contact details, emergency contact</li>
    <li><strong>Private</strong> — address, date of birth (visible only to Bureau)</li>
    <li><strong>Diving</strong> — certification levels (from lookup table), dive count, training enrollments</li>
    <li><strong>Medical</strong> — upload/verify medical certificates, compliance status</li>
    <li><strong>Licences</strong> — federation licence numbers and request tracking</li>
    <li><strong>Documents</strong> — ID scans, insurance, other uploads</li>
    <li><strong>Emails</strong> — manage up to 5 email addresses per member</li>
    <li><strong>Equipment</strong> — view current equipment loans</li>
</ul>

<h5>Certification Levels</h5>
<p>The system includes {{ \App\Models\CertificationLevel::count() }} certification levels across {{ \App\Models\Federation::count() }} federations. Members can add multiple certifications and mark one as "primary" for display. The system learns display preferences over time via the <code>display_priority</code> counter.</p>

<h5>Roles & Statuses</h5>
<p>Change a member's role via the Members list (dropdown). Change status via their profile. Fee multipliers are tied to statuses — see Settings.</p>

<h5>Impersonation</h5>
<p>Bureau Master can impersonate any member to see the system from their perspective. Click "Impersonate" on the members list. A yellow banner shows during impersonation with a "Stop" link. All impersonation actions are audit-logged.</p>

<h5>Self-Registration</h5>
<p>New users register at <code>/register</code> and get the <code>pending</code> role. Bureau Master must change their role to <code>member</code> (or another role) to grant access.</p>
@endsection
