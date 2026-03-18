@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>Equipment Inventory</h5>
<p>Go to Administration → Equipment. Track all club equipment with:</p>
<ul>
    <li>Name, type (BCD, regulator, tank, wetsuit, mask, fins, computer, other)</li>
    <li>Serial number, purchase date, condition</li>
    <li>Status: available, on_loan, maintenance_required, retired</li>
</ul>

<h5>Maintenance Scheduling</h5>
<p>When equipment is created, the system auto-generates maintenance tasks from the rules defined in Settings → Equipment Maintenance Rules.</p>
<p>When a maintenance task is completed, the system automatically schedules the next one based on the interval.</p>
<p>If mandatory maintenance is overdue, the equipment status changes to <code>maintenance_required</code> and cannot be loaned out.</p>

<h5>Loan Management</h5>
<ol>
    <li>Go to an equipment item's detail page</li>
    <li>Click "Loan" and select a member</li>
    <li>The item status changes to <code>on_loan</code></li>
    <li>When returned, click "Return" — status reverts to <code>available</code> (or <code>maintenance_required</code> if overdue)</li>
</ol>
<p>Members can see their current loans on their profile's Equipment tab.</p>

<h5>Maintenance Rules</h5>
<p>Go to Settings → Equipment Maintenance Rules. Define per equipment type:</p>
<ul>
    <li>Maintenance name (e.g., "Annual service")</li>
    <li>Interval in months</li>
    <li>Mandatory flag — affects compliance status</li>
    <li>Regulation reference (optional)</li>
</ul>
@endsection
