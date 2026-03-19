@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>Inter-Club Partnerships</h5>
<p>The federation API allows two clubs running DivingClub to share events and accept cross-registrations.</p>

<h5>Setting Up a Partnership</h5>
<ol>
    <li>Go to Administration → Partnerships → Add Partner</li>
    <li>Enter the partner club's name and base URL (e.g. <code>https://club-b.example.com</code>)</li>
    <li>System generates a <strong>Key ID</strong> and <strong>Secret</strong> (symmetric key)</li>
    <li>Send these credentials to the partner club's admin (via secure channel)</li>
    <li>The partner does the same and sends you their credentials</li>
    <li>Edit the partnership, paste their Key ID and Secret</li>
    <li>Both clubs now have bidirectional API access</li>
</ol>

<h5>Federated Events</h5>
<p>When creating an event, check <strong>"Federated"</strong> and set the number of external slots. This event becomes visible to partner clubs via the API.</p>

<h5>External Registrations</h5>
<p>Go to Administration → Partnerships → External Registrations to see incoming registration requests from partner clubs. Each request includes the member's name, certification level, and medical validity. Approve or reject each one.</p>

<h5>Browsing Partner Events</h5>
<p>Click <strong>Browse Events</strong> on a partnership to see the partner club's federated events with available external slots.</p>

<h5>Security</h5>
<p>All API calls are authenticated with HMAC-SHA256 signatures using the shared secret. Requests include a timestamp to prevent replay attacks.</p>
@endsection
