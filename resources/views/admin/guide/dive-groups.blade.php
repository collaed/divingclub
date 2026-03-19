@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>Dive Group Planner (Palanquées)</h5>
<p>For dive events, the Dive Group Planner helps organize divers into safe buddy groups based on federation rules.</p>

<h5>How It Works</h5>
<ol>
    <li>Open a dive event → click <strong>Dive Groups</strong></li>
    <li>Create groups (palanquées) and assign registered divers</li>
    <li>Click <strong>Validate</strong> to check all groups against federation rules</li>
</ol>

<h5>Rule Engine</h5>
<p>The system includes {{ \App\Models\DiveGroupRule::count() }} rules across 5 federations (LIFRAS, FFESSM, BSAC, PADI, CMAS). Rules cover:</p>
<ul>
    <li><strong>Group size</strong> — min/max divers per group</li>
    <li><strong>Leader requirements</strong> — minimum cert level for the group leader</li>
    <li><strong>Depth limits</strong> — max depth per cert level</li>
    <li><strong>Supervision ratios</strong> — e.g. max 4 beginners per instructor</li>
    <li><strong>Mixed-level rules</strong> — which cert levels can dive together</li>
</ul>

<h5>Managing Rules</h5>
<p>Go to Administration → Dive Group Rules to add, edit, or delete rules. Each rule specifies: federation, rule type, cert level constraints, and the regulation reference (e.g. Code du Sport Art. A322-71).</p>

<h5>Validation Output</h5>
<p>The validator checks each group and returns:</p>
<ul>
    <li><span class="badge bg-success">Valid</span> — group composition meets all rules</li>
    <li><span class="badge bg-danger">Violation</span> — specific rule broken, with explanation</li>
    <li><span class="badge bg-warning text-dark">Warning</span> — medical cert expiring within 30 days</li>
</ul>
@endsection
