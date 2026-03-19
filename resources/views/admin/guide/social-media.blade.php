@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>Social Media Auto-Publish</h5>
<p>The system can automatically post event photos to a Facebook group when all GDPR conditions are met.</p>

<h5>Three-Gate GDPR Check</h5>
<p>A photo is only published when <strong>all three</strong> conditions are true:</p>
<ol>
    <li><strong>Author consent</strong> — the uploader checked the GDPR consent checkbox when uploading</li>
    <li><strong>Closed group</strong> — the admin confirmed the Facebook group is a closed/private group (Settings → Technical → Social Media)</li>
    <li><strong>Auto-publish enabled</strong> — the admin toggled auto-publish on in settings</li>
</ol>
<p>If any condition fails, the photo stays on the website only.</p>

<h5>Setup</h5>
<ol>
    <li>Create a Facebook App at <a href="https://developers.facebook.com" target="_blank">developers.facebook.com</a></li>
    <li>Get a Page Token with <code>publish_to_groups</code> permission</li>
    <li>Add <code>FACEBOOK_PAGE_TOKEN=your_token</code> to <code>.env</code></li>
    <li>Go to Settings → Technical → Social Media Auto-Publish</li>
    <li>Enter the Facebook Group ID, confirm it's a closed group, enable auto-publish</li>
</ol>

<h5>Publish Log</h5>
<p>Every publish attempt is logged in the <code>social_publish_logs</code> table with status (pending/published/failed), external post ID, and error messages. This is visible in the database for debugging.</p>

<h5>Photo Upload Flow</h5>
<p>When a member uploads event photos:</p>
<ol>
    <li>Must have photo publication consent in Privacy settings</li>
    <li>Must check the GDPR consent checkbox on the upload form</li>
    <li>Photos are quality-scored automatically (resolution, size, orientation)</li>
    <li>If all three gates pass, the photo is posted to Facebook immediately</li>
</ol>
@endsection
