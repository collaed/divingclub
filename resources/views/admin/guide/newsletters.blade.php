@extends('admin.guide.partials.guide-layout')
@section('guide-title', __('Newsletters'))
@section('guide-content')
<h5>📬 {{ __('Creating a Newsletter') }}</h5>
<ol>
    <li>Go to <strong>Admin → Newsletters → Create</strong></li>
    <li>Set the title and month</li>
    <li>Click a slot card (1-5), then pick an article from the sidebar</li>
    <li>For each slot, you can optionally:
        <ul>
            <li><strong>Edit the teaser text</strong> — overrides the auto-generated excerpt</li>
            <li><strong>Set a custom URL</strong> — links to an external page instead of the article</li>
        </ul>
    </li>
    <li>Click <strong>🐠 Scatter Decorations</strong> to add marine-themed SVG decorations</li>
    <li>Click <strong>Save Draft</strong></li>
</ol>

<h5 class="mt-4">📧 {{ __('Testing & Sending') }}</h5>
<ul>
    <li><strong>Preview Email</strong> — opens the email template in a new tab</li>
    <li><strong>Send test to me</strong> — sends the newsletter to your own email with [TEST] prefix</li>
    <li><strong>Submit for Approval</strong> — sends to bureau members for review (3 approvals needed)</li>
    <li><strong>Send for Comments</strong> — opens a mailto: link to all bureau members with the preview link</li>
    <li><strong>Send Newsletter</strong> — available after 3 approvals, sends to all verified members</li>
</ul>

<h5 class="mt-4">🌐 {{ __('Article Links') }}</h5>
<p>By default, "Read more" links point to your app. To point them to an external site (e.g. the old Joomla site):</p>
<ol>
    <li>Go to <strong>Admin → Settings → Newsletter Settings</strong></li>
    <li>Set the <strong>Article Base URL</strong> (e.g. <code>https://clubcep.eu</code>)</li>
    <li>Or use the <strong>Custom URL</strong> field per slot for individual overrides</li>
</ol>

<h5 class="mt-4">🇬🇧 {{ __('English Links') }}</h5>
<p>Each article card in the email shows a small "EN ›" link at the bottom-left if an English translation exists. The main "Lire la suite →" link stays French.</p>
@endsection
