@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>Two Voting Modes</h5>
<table class="table table-sm">
    <thead><tr><th>Mode</th><th>Behavior</th></tr></thead>
    <tbody>
        <tr><td><strong>Simple</strong></td><td>Voter can change their choice until the vote closes. Token is stored on the ballot (auditable).</td></tr>
        <tr><td><strong>Election</strong></td><td>Anonymous and irreversible. Once cast, the ballot is stored with <code>token_hash=NULL</code> — the link between voter and ballot is permanently severed.</td></tr>
    </tbody>
</table>

<h5>Vote Options</h5>
<table class="table table-sm">
    <thead><tr><th>Option</th><th>Effect</th></tr></thead>
    <tbody>
        <tr><td><strong>Allow multiple selections</strong></td><td>Voters can select more than one option (endorsement-style). Displayed as checkboxes instead of radio buttons.</td></tr>
        <tr><td><strong>Allow vote change</strong></td><td>Voters can re-submit until the vote closes. Enabled by default for simple mode. Ignored for elections (always irreversible).</td></tr>
        <tr><td><strong>Show results publicly</strong></td><td>Live results (progress bars with percentages) are visible to voters on the voting page.</td></tr>
    </tbody>
</table>

<h5>Creating a Vote</h5>
<ol>
    <li>Go to Administration → Votes → Create</li>
    <li>Set title, description, mode (simple/election), open/close dates</li>
    <li>Toggle multi-select, changeable, and public results as needed</li>
    <li>Add options (at least 2)</li>
    <li>Save the vote</li>
</ol>
<p>Votes can also be attached to trip proposal articles — the vote is then embedded directly in the article page.</p>

<h5>Token Generation</h5>
<p>Click "Generate Tokens" on the vote detail page. This creates a unique 128-character token per eligible member. Tokens are sent via the email system — each member gets a link like:</p>
<pre class="bg-light p-2 rounded"><code>https://your-domain.lu/vote/abc123...xyz</code></pre>
<p>No login is required to vote — the token is the authentication.</p>

<h5>Auto Open/Close</h5>
<p>The scheduler automatically opens votes at their <code>opens_at</code> time and closes them at <code>closes_at</code>. You can also manually open/close/cancel from the admin page.</p>

<h5>Results</h5>
<p>Results are shown as progress bars with percentages. When "Show results publicly" is enabled, voters see live results on the voting page. For election mode, individual ballots are never exposed — only aggregate counts.</p>
@endsection
