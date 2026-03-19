@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>Fee Calculation</h5>
<p>Formula: <code>final = (base × status_multiplier × (1 − age_discount)) + optional_components</code></p>
<table class="table table-sm">
    <thead><tr><th>Factor</th><th>Value</th></tr></thead>
    <tbody>
        <tr><td>Age &lt; 18</td><td>50% discount</td></tr>
        <tr><td>Age ≥ 65</td><td>25% discount</td></tr>
        <tr><td>Honorary status</td><td>Always €0</td></tr>
        <tr><td>Status multiplier</td><td>Configured per status in Settings</td></tr>
    </tbody>
</table>

<h5>Fee Components</h5>
<p>Go to Payments → Fee Components. Create:</p>
<ul>
    <li>One <strong>base</strong> component (e.g., "Base Membership €120")</li>
    <li>Optional components: insurance levels, double affiliation, etc.</li>
</ul>
<p>Components can be tied to a specific season or be season-independent (applies to all).</p>

<h5>Generating Fees</h5>
<p>From the Payments page, select a member and click "Generate Fee". The system calculates the amount and creates a <code>payment_expected</code> record with a unique communication string:</p>
<pre class="bg-light p-2 rounded"><code>CLUB-2026-42-DUPONT MARIE+insurance_standard</code></pre>
<p>This string is used for bank reconciliation matching.</p>

<h5>Bank Reconciliation</h5>
<ol>
    <li>Go to Payments → Reconciliation</li>
    <li><strong>Import</strong> — paste bank statement text (format: <code>date;amount;communication;counterparty</code> per line)</li>
    <li><strong>Auto-match</strong> — system fuzzy-matches transactions against expected payments using communication string (+80), amount (+20), last name (+30), and IBAN (+50). Score 0–100, threshold 60 to auto-match</li>
    <li><strong>Review</strong> — confirm correct matches, ignore false positives</li>
    <li>Confirmed matches update payment status to "paid"</li>
</ol>

<h5>SEPA QR Codes</h5>
<p>Members can generate a SEPA EPC QR code for their payment. Scanning it in a banking app pre-fills the transfer with IBAN, amount, and communication string. Configure <code>CLUB_IBAN</code> in <code>.env</code>.</p>

<h5>Event Deposits</h5>
<p>Events with deposit schedules automatically create payment_expected records per instalment per registered participant.</p>
@endsection
