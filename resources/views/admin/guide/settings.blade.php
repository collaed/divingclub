@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>Settings Page</h5>
<img src="/images/guide/admin-settings.png" class="img-fluid rounded border mb-3" alt="Settings" style="max-height:300px">
<p>Go to Administration → Settings. The page has accordion sections:</p>

<h5>Federations</h5>
<p>Add/edit/delete diving federations. Pre-configured: FFESSM, LIFRAS, FLASSA, NELOS, VDST, PADI, SSI, UCPA, BSAC, NASDS, CMAS.</p>
<p>Each federation has certification levels (seeded automatically). Members can hold certifications from multiple federations.</p>

<h5>Member Statuses & Fee Multipliers</h5>
<p>Each status has a slug (used in code) and a fee multiplier. The multiplier is applied to the base fee during fee calculation.</p>
<p>Pre-configured statuses use French slugs: <code>actif</code>, <code>fonctionnaire</code>, <code>honoraire</code>, <code>junior</code>, <code>famille</code>, <code>membre_de_droit</code>.</p>

<h5>Medical Compliance Rules</h5>
<p>Define which medical certificates are required per federation, age bracket, and type. The system uses the most restrictive rule when multiple apply.</p>

<h5>Equipment Maintenance Rules</h5>
<p>Define maintenance schedules per equipment type. Mandatory rules affect equipment availability.</p>

<h5>Theme & Appearance</h5>
<p>Customize the look of the entire application:</p>
<ul>
    <li><strong>Presets</strong> — one-click themes: Ocean (default navy), Coral (red), Lagoon (teal), Abyss (deep blue), Tropical (cyan), Arctic (grey)</li>
    <li><strong>Custom colors</strong> — primary, secondary, accent, header gradient, footer background</li>
    <li><strong>Article type backgrounds</strong> — set a distinct background color for each article type (news, safety, training, etc.)</li>
    <li><strong>Branding</strong> — logo emoji, text, club full name</li>
    <li><strong>Layout</strong> — width (normal/wide/extra-wide/full), header bubble animation on/off</li>
    <li><strong>Logo upload</strong> — custom image logo</li>
</ul>
<p>Changes take effect immediately (cached for 5 minutes).</p>

<h5>API Keys</h5>
<p>Shows the status of all configured API integrations. See <a href="{{ route('admin.guide.show', 'api-keys') }}">API Keys & OAuth Setup</a> for details.</p>
@endsection
