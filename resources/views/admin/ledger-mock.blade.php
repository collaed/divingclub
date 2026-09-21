@php
    /* MOCK DATA — sample lines from the Jan–Sep 2026 bank export; individuals shown as initials. Nothing is saved. */
    $states = [
        'expected'   => ['label' => 'Expected',     'icon' => '✓✓', 'hint' => 'Matches an amount the system already holds'],
        'recognised' => ['label' => 'Recognised',   'icon' => '✓',  'hint' => 'Payer and purpose known, no amount to check against'],
        'confirm'    => ['label' => 'To confirm',   'icon' => '≈',  'hint' => 'Probable, or the amount differs from what was expected'],
        'unknown'    => ['label' => 'Unknown',      'icon' => '?',  'hint' => 'Nothing recognised — needs you'],
        'loop'       => ['label' => 'Paired',       'icon' => '⇄',  'hint' => 'Part of a closed loop that nets to zero'],
    ];
    $inbox = [
        ['05/01', 'Destinations en Europe', 'REMB CLIENT CLUB EUROPEEN DE PLONGEE — OMN2025-318326', 242.12, 'loop', 'Pairs with 3 refunds of €80.91 + €79.52 + €81.69 = €242.12. Loop nets to 0.', '31', 'Loop “Rent-a-car withdrawals”', null],
        ['08/01', 'E. C. (member)', 'Remboursement retrait carte effectué par Al Maha Rent-a-Car', -80.91, 'loop', 'Part of loop “Rent-a-car withdrawals” (1 in → 3 out).', '31', 'Loop “Rent-a-car withdrawals”', 'BaP ✓'],
        ['05/01', 'F. D. (member)', 'Cotisation et RC', 153.50, 'expected', 'Equals the amount this member committed to for 2026 (€153.50).', '21', 'Membership 2026', null],
        ['22/01', 'F. D. (member)', 'Complément Cotisation et RC', 5.00, 'confirm', 'Same member and season as €153.50 two weeks earlier — attach as a top-up to that membership?', '21', 'Membership 2026', null],
        ['16/01', 'P. T. (member)', 'Cotisation 2026 — Sympathisant', 30.00, 'expected', 'Sympathisant is €30.00: exact match.', '21', 'Membership 2026', null],
        ['14/09', 'E. P. (member)', 'Cotisation externe', 215.00, 'expected', '= Externe €190 + Loisir 1 insurance €25 (unique combination) → insurance goes to the “to register” list.', '21', 'Membership 2027 · insurance', null],
        ['13/01', 'M. M. (member)', 'Cotisation 2026 — Membre externe + Licence', 186.20, 'confirm', 'Member recognised; €186.20 matches no tariff combination — a discount? Note also says “symp. 30E annulée”.', '21', 'Membership 2026', null],
        ['17/04', 'Cercle Sportif Communauté Européenne', 'SUBSIDE 2026', 3000.00, 'expected', 'Budget line “Subsides” expects €3,000.00.', '34', 'Budget: Subsides', null],
        ['04/03', 'K. R. (member)', '2ème acompte séjour Juan-les-Pins', 500.00, 'expected', 'Registered on the JLP event; 2nd deposit in the payment schedule is €500.00.', '24', 'Operation: Juan-les-Pins', null],
        ['22/06', 'R. S. (member)', 'Solde sortie Juan-les-Pins (1,100 + 477.19)', -1577.19, 'expected', 'Trip settlement computed a balance of €1,577.19 for this participant.', '86', 'Operation: Juan-les-Pins', 'BaP missing'],
        ['19/01', 'SARL Easy Dive', 'Acompte Devis 947 du 20.12.2025', -2800.00, 'recognised', 'Known supplier (2 payments this year); quote 947 suggests the JLP trip.', '86', 'Operation: Juan-les-Pins?', 'BaP ✓ 260116 BaP EasyDive.pdf'],
        ['18/06', 'LUXAIR S.A.', 'GRP Collart 4366013 — Cap Vert', -6639.00, 'recognised', 'Airline; communication names Cap Vert.', '86', 'Operation: Cap Vert', 'BaP missing'],
        ['22/05', 'D. G. family', 'Famille — 1er acompte Cap Vert 2026', 2135.24, 'confirm', 'Registered on Cap Vert but €2,135.24 is not a multiple of the €784 deposit — 3 people plus a supplement?', '24', 'Operation: Cap Vert', null],
        ['31/07', 'K. C. (member)', 'Paiement amende Todi', 181.34, 'loop', 'Pairs with the payment of the same amount to Voyages Emile Weber on 17/08. Loop nets to 0.', '86', 'Loop “Todi fine”', null],
        ['17/08', 'Voyages Emile Weber SARL', 'Ref. 91/2026/1111', -181.34, 'loop', 'Closes loop “Todi fine”: +181.34 / −181.34.', '86', 'Loop “Todi fine”', 'BaP ✓'],
        ['08/01', 'Lafont Assurances', 'Bordereau F00413219', -24.20, 'recognised', 'Insurance broker, 4 bordereaux this year.', '82', 'Cost centre: Insurance', 'BaP missing'],
        ['08/01', 'Recette communale de Steinfort', 'Facture 1844 / PI2025020083', -272.71, 'recognised', 'Pool rental, recurring quarterly invoice.', '52', 'Cost centre: Pool rental', 'BaP ✓ 260106 BaP Steinfort.pdf'],
        ['10/03', 'Comité Régional Grand Est FFESSM', 'Facture 20260355 — 06970240', -1512.00, 'recognised', 'Federation invoice (licences).', '81', 'Cost centre: Federation', 'BaP ✓'],
        ['21/01', 'Frais de tenue de compte — Zebra', '', -3.50, 'recognised', 'Monthly bank fee — rule “Zebra fee” applied automatically.', '85', 'Cost centre: Bank', 'not needed'],
        ['09/02', 'S. O. (member)', 'Nemo33 – Oksana + GD', 62.00, 'recognised', 'Small payment during a Nemo33 session week; the amount is not in any schedule.', '24', 'Operation: Nemo33 Feb', null],
        ['15/01', 'Commission Européenne OIL Rest. Admi.', '5988 — CARES-CONF-2025-12', -1600.00, 'unknown', 'Counterparty not in the directory and no similar past payment. Who is this, and what for?', '?', '—', 'BaP missing'],
        ['15/09', 'M. T. (member)', 'Remboursement acompte Cabo Verde Diving 27–31/10/2026', -2080.00, 'unknown', 'Refund to a member for a trip that is not created yet.', '?', '—', 'BaP missing'],
    ];
    $stateCounts = ['expected' => 61, 'recognised' => 74, 'confirm' => 23, 'unknown' => 15, 'loop' => 30];
    $cas = [
        ['Recettes', '21', 'Cotisations', 3650, 5750, 6232, 2143],
        ['Recettes', '22', "Droits d'entraînement", 0, 0, 0, 0],
        ['Recettes', '23', 'Divers (licences)', 3425, 4383, 4848, 273],
        ['Recettes', '24', 'Sorties club', 2400, 18913, 26757, 44483],
        ['Recettes', '31', 'Recettes (remboursements)', 80, 113, 1119, 242],
        ['Recettes', '32', 'Dons', 0, 0, 0, 0],
        ['Recettes', '34', 'Subsides', 4362, 4362, 4582, 3000],
        ['Dépenses', '51', 'Achats et entretiens', 334, 166, 3452, 1151],
        ['Dépenses', '52', 'Locations (hors École Europ.)', 1770, 2226, 2614, 843],
        ['Dépenses', '53', 'Divers (à préciser)', 0, 575, 0, 1600],
        ['Dépenses', '61', 'Moniteurs / entraîneurs', 0, 500, 90, 0],
        ['Dépenses', '62', 'Équipements', 330, 0, 0, 0],
        ['Dépenses', '63', 'Divers (remboursements cotisation)', 1760, 80, 0, 0],
        ['Dépenses', '71', 'Locations', 0, 0, 561, 0],
        ['Dépenses', '72', 'Matériel', 0, 2845, 833, 0],
        ['Dépenses', '81', 'Licences, cotisations', 2290, 1896, 5262, 5626],
        ['Dépenses', '82+87', 'Assurances', 1503, 1929, 1815, 299],
        ['Dépenses', '83', 'Assemblée générale', 362, 0, 0, 234],
        ['Dépenses', '84', 'Réceptions', 0, 0, 3979, 0],
        ['Dépenses', '85', 'Frais de gestion', 45, 19, 112, 28],
        ['Dépenses', '86', 'Sorties club', 3083, 19753, 29139, 35276],
    ];
    $statements = [
        [1, 24, 24568.27, 1894.62, 5511.67, 20951.22], [2, 15, 20951.22, 1761.65, 3.50, 22709.37], [3, 32, 22709.37, 6240.00, 2732.73, 26216.64],
        [4, 17, 26216.64, 7177.55, 1407.19, 31987.00], [5, 26, 31987.00, 16353.24, 3.50, 48336.74], [6, 52, 48336.74, 5137.19, 29195.68, 24278.25],
        [7, 26, 24278.25, 6953.53, 3146.87, 28084.91], [8, 5, 28084.91, 2875.50, 482.35, 30478.06], [9, 6, 30478.06, 1748.00, 2814.50, 29411.56],
    ];
    $eur = fn (float $v): string => number_format($v, 2, ',', ' ');
@endphp
<x-admin-layout :title="__('Ledger (mock)')">
<style>
    .lg-pill { display:inline-flex; align-items:center; gap:.3rem; padding:.1rem .55rem; border-radius:999px; font-size:.75rem; font-weight:600; white-space:nowrap; }
    .lg-expected   { --c:#146c43; --bg:#146c43; --fg:#fff; }
    .lg-recognised { --c:#6fbf87; --bg:#d3f0dc; --fg:#14532d; }
    .lg-confirm    { --c:#e0a800; --bg:#fff0bd; --fg:#664d03; }
    .lg-unknown    { --c:#dc3545; --bg:#f8d7da; --fg:#842029; }
    .lg-loop       { --c:#0aa2c0; --bg:#cff4fc; --fg:#055160; }
    .lg-pill { background:var(--bg); color:var(--fg); }
    tr.lg-row > td:first-child { border-left:5px solid var(--c); }
    [data-bs-theme="dark"] .lg-recognised { --bg:#1d4a2c; --fg:#c9f0d5; } [data-bs-theme="dark"] .lg-confirm { --bg:#5c4700; --fg:#ffe69c; }
    [data-bs-theme="dark"] .lg-unknown { --bg:#58151c; --fg:#f1aeb5; } [data-bs-theme="dark"] .lg-loop { --bg:#032830; --fg:#9eeaf9; }
    .lg-chip { display:inline-block; padding:.05rem .45rem; border-radius:.35rem; font-size:.72rem; background:var(--bs-tertiary-bg); border:1px solid var(--bs-border-color); }
    .lg-num { font-variant-numeric: tabular-nums; text-align:right; white-space:nowrap; }
    .lg-in { color:#146c43; } .lg-out { color:#b02a37; }
    .lg-bar { height:.5rem; border-radius:.25rem; background:var(--bs-tertiary-bg); overflow:hidden; }
    .lg-bar > span { display:block; height:100%; background:#0d6efd; }
    .lg-why { font-size:.8rem; color:var(--bs-secondary-color); }
</style>

<div class="alert alert-warning d-flex align-items-center gap-2 py-2">
    <strong>Design mock.</strong>
    <span>Sample lines from the Jan–Sep 2026 bank export (members shown as initials), the club’s own categories from the 2020–2022 “Tableau Compta”. Buttons do nothing; nothing is saved.</span>
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0">Ledger 2026</h4>
        <small class="text-muted">Account LU21 0019 7855 8919 6000 · 203 lines imported from the bank export</small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" type="button">Import statement</button>
        <button class="btn btn-sm btn-outline-primary" type="button">Export in the club’s Tableau Compta format</button>
    </div>
</div>

{{-- Audit readiness --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-lg-3"><div class="card dc-card p-3 h-100"><div class="small text-muted">Bank balance check</div><div class="h5 mb-0 lg-in">✓ 203 / 203 lines</div><div class="lg-why">Opening + movements = closing on all 9 statements</div></div></div>
    <div class="col-6 col-lg-3"><div class="card dc-card p-3 h-100"><div class="small text-muted">Classified by the bureau</div><div class="h5 mb-1">158 / 203 · 78%</div><div class="lg-bar"><span style="width:78%"></span></div></div></div>
    <div class="col-6 col-lg-3"><div class="card dc-card p-3 h-100"><div class="small text-muted">Expenses with a bon à payer</div><div class="h5 mb-1">41 / 70 · 59%</div><div class="lg-bar"><span style="width:59%; background:#e0a800"></span></div></div></div>
    <div class="col-6 col-lg-3"><div class="card dc-card p-3 h-100"><div class="small text-muted">Reviewers signed off</div><div class="h5 mb-0">0 / 2</div><div class="lg-why">Financial reviewers sign once the period is complete</div></div></div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-inbox" type="button">Inbox <span class="badge bg-danger">15</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-ops" type="button">Operations <span class="badge bg-secondary">6</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cas" type="button">Categories</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-budget" type="button">Budget vs actual</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-audit" type="button">Audit</button></li>
</ul>

<div class="tab-content">

{{-- ===================== INBOX ===================== --}}
<div class="tab-pane fade show active" id="tab-inbox">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        @foreach($states as $key => $s)
            <span class="lg-pill lg-{{ $key }}" title="{{ $s['hint'] }}">{{ $s['icon'] }} {{ $s['label'] }} · {{ $stateCounts[$key] }}</span>
        @endforeach
        <span class="ms-auto d-flex gap-2">
            <button class="btn btn-sm btn-success" type="button">Confirm all “Expected” (61)</button>
            <button class="btn btn-sm btn-outline-success" type="button">Review “Recognised” (74)…</button>
        </span>
    </div>
    <p class="lg-why mb-2">Deep green: the amount is what the system expected. Light green: it makes sense, but there was no amount to check. Every state also carries an icon, so the list reads without colour.</p>

    <div class="card dc-card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Date</th><th>Counterparty · communication</th><th class="text-end">Amount</th><th>What the system thinks</th><th>Cat.</th><th>Linked to</th><th>Bon à payer</th><th></th></tr></thead>
                <tbody>
                @foreach($inbox as [$date, $who, $comm, $amt, $st, $why, $catCode, $link, $bap])
                    <tr class="lg-row lg-{{ $st }}">
                        <td class="small">{{ $date }}</td>
                        <td style="min-width:14rem"><strong>{{ $who }}</strong><div class="lg-why">{{ $comm }}</div></td>
                        <td class="lg-num {{ $amt >= 0 ? 'lg-in' : 'lg-out' }}">{{ $amt >= 0 ? '+' : '−' }}{{ $eur(abs($amt)) }}</td>
                        <td style="min-width:18rem"><span class="lg-pill lg-{{ $st }}">{{ $states[$st]['icon'] }} {{ $states[$st]['label'] }}</span><div class="lg-why mt-1">{{ $why }}</div></td>
                        <td>@if($catCode === '?')<span class="lg-chip">—</span>@else<span class="lg-chip">{{ $catCode }}</span>@endif</td>
                        <td><span class="lg-chip">{{ $link }}</span></td>
                        <td class="small">
                            @if($bap === null || $bap === 'not needed')<span class="text-muted">—</span>
                            @elseif(str_starts_with($bap, 'BaP ✓'))<span class="lg-in">{{ $bap }}</span>
                            @else<span class="text-warning">⚠ {{ $bap }}</span>@endif
                        </td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-outline-success" type="button" title="Confirm">✓</button>
                            <button class="btn btn-sm btn-outline-secondary" type="button" title="Change category, link, or attach a bon à payer">⋯</button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-muted d-flex justify-content-between flex-wrap gap-2">
            <span>Showing 22 of 203 lines. Confirming a line offers “remember this for next time” (creates a counterparty rule).</span>
            <span>Bulk actions never confirm amber or red rows.</span>
        </div>
    </div>
</div>

{{-- ===================== OPERATIONS ===================== --}}
<div class="tab-pane fade" id="tab-ops">
    <p class="lg-why">An operation groups the lines that belong together and shows their running net. Closed loops net to zero; trips settle to a small result; cost centres never close.</p>
    <div class="row g-3">
        <div class="col-lg-6"><div class="card dc-card h-100"><div class="card-body">
            <div class="d-flex justify-content-between"><h6 class="mb-0">Juan-les-Pins 2026</h6><span class="lg-pill lg-confirm">≈ Doesn’t balance yet</span></div>
            <div class="lg-why mb-2">Trip · linked to the event · budget ≈ €25 000 · expected result about +€450</div>
            <div class="row text-center mb-2"><div class="col"><div class="small text-muted">Collected</div><div class="lg-in fw-semibold">+20 022,19</div></div><div class="col"><div class="small text-muted">Paid out</div><div class="lg-out fw-semibold">−24 298,34</div></div><div class="col"><div class="small text-muted">Net</div><div class="fw-semibold lg-out">−4 276,15</div></div></div>
            <div class="lg-why mb-2">78 lines linked. The net is far from the expected +€450, so the system looks for what is missing.</div>
            <div class="alert alert-warning py-2 small mb-2"><strong>11 lines proposed:</strong> payments from registered participants inside the trip dates that mention “JLP”, “Juans” or only a name (≈ +€4 700). Accepting them moves the net towards +€450.</div>
            <button class="btn btn-sm btn-outline-primary" type="button">Review 11 proposed lines</button>
        </div></div></div>

        <div class="col-lg-6"><div class="card dc-card h-100"><div class="card-body">
            <div class="d-flex justify-content-between"><h6 class="mb-0">Cap Vert 2026</h6><span class="lg-pill lg-recognised">✓ Open — travelling in October</span></div>
            <div class="lg-why mb-2">Trip · settles after the trip · payment schedule: 3 instalments per person</div>
            <div class="row text-center mb-2"><div class="col"><div class="small text-muted">Collected</div><div class="lg-in fw-semibold">+19 964,18</div></div><div class="col"><div class="small text-muted">Paid out</div><div class="lg-out fw-semibold">−6 639,00</div></div><div class="col"><div class="small text-muted">Net so far</div><div class="fw-semibold lg-in">+13 325,18</div></div></div>
            <div class="lg-bar mb-1"><span style="width:33%"></span></div><div class="lg-why">Costs paid: 33% of the collected amount · 2 deposits short of the schedule</div>
        </div></div></div>

        <div class="col-lg-6"><div class="card dc-card h-100"><div class="card-body">
            <div class="d-flex justify-content-between"><h6 class="mb-0">Todi fine</h6><span class="lg-pill lg-expected">✓✓ Closed loop</span></div>
            <div class="lg-why mb-2">Pass-through · detected automatically: equal and opposite amounts, 17 days apart</div>
            <div class="d-flex justify-content-between"><span>31/07 · K. C. → club</span><span class="lg-in">+181,34</span></div>
            <div class="d-flex justify-content-between"><span>17/08 · club → Voyages Emile Weber</span><span class="lg-out">−181,34</span></div>
            <hr class="my-2"><div class="d-flex justify-content-between fw-semibold"><span>Net</span><span>0,00</span></div>
        </div></div></div>

        <div class="col-lg-6"><div class="card dc-card h-100"><div class="card-body">
            <div class="d-flex justify-content-between"><h6 class="mb-0">Rent-a-car withdrawals</h6><span class="lg-pill lg-expected">✓✓ Closed loop (1 → 3)</span></div>
            <div class="lg-why mb-2">One refund from the travel agency covers three members’ card withdrawals</div>
            <div class="d-flex justify-content-between"><span>05/01 · Destinations en Europe → club</span><span class="lg-in">+242,12</span></div>
            <div class="d-flex justify-content-between"><span>08–09/01 · club → 3 members</span><span class="lg-out">−242,12</span></div>
            <hr class="my-2"><div class="d-flex justify-content-between fw-semibold"><span>Net</span><span>0,00</span></div>
        </div></div></div>

        <div class="col-lg-6"><div class="card dc-card h-100"><div class="card-body">
            <div class="d-flex justify-content-between"><h6 class="mb-0">Pool rental — Steinfort</h6><span class="lg-pill lg-recognised">✓ Cost centre</span></div>
            <div class="lg-why mb-2">Ongoing · category 52 · budget €900 / year</div>
            <div class="d-flex justify-content-between"><span>YTD (3 invoices)</span><strong class="lg-out">−842,93</strong></div>
            <div class="lg-bar my-1"><span style="width:94%; background:#e0a800"></span></div><div class="lg-why">94% of the yearly budget used</div>
        </div></div></div>

        <div class="col-lg-6"><div class="card dc-card h-100"><div class="card-body">
            <div class="d-flex justify-content-between"><h6 class="mb-0">Equipment &amp; maintenance</h6><span class="lg-pill lg-recognised">✓ Cost centre</span></div>
            <div class="lg-why mb-2">Ongoing · category 51 · budget €2 000 / year</div>
            <div class="d-flex justify-content-between"><span>YTD (compressor air, O₂ sensor, parts, inflation)</span><strong class="lg-out">−1 151,00</strong></div>
            <div class="lg-bar my-1"><span style="width:58%"></span></div><div class="lg-why">58% of the yearly budget used</div>
        </div></div></div>
    </div>
</div>

{{-- ===================== CATEGORIES ===================== --}}
<div class="tab-pane fade" id="tab-cas">
    <p class="lg-why">The club’s own categories (the “Rubriques” of the Tableau Compta). 2020–2022 come from the workbooks; 2026 is the bank export so far, before you confirm anything.</p>
    <div class="row g-3">
        @foreach(['Recettes' => 'Income', 'Dépenses' => 'Expenses'] as $kind => $title)
            @php $lines = array_filter($cas, fn ($c) => $c[0] === $kind); @endphp
            <div class="col-xl-6"><div class="card dc-card"><div class="card-header">{{ $title }}</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Cat.</th><th></th><th class="lg-num">2020</th><th class="lg-num">2021</th><th class="lg-num">2022</th><th class="lg-num">2026 so far</th></tr></thead>
                    <tbody>
                    @foreach($lines as [$k, $code, $label, $a, $b, $c, $d])
                        <tr><td><span class="lg-chip">{{ $code }}</span></td><td>{{ $label }}</td>
                            @foreach([$a, $b, $c, $d] as $v)<td class="lg-num">{{ $v ? number_format($v, 0, ',', ' ') : '—' }}</td>@endforeach</tr>
                    @endforeach
                    <tr class="fw-semibold"><td></td><td>Total</td>
                        @foreach([3, 4, 5, 6] as $i)<td class="lg-num">{{ number_format(array_sum(array_map(fn ($c) => $c[$i], $lines)), 0, ',', ' ') }}</td>@endforeach</tr>
                    </tbody>
                </table></div>
            </div></div>
        @endforeach
    </div>
    <p class="lg-why mt-2">Trips (categories 24 and 86) dominate: in 2022 the club took in €26 757 and paid out €29 139 for them. This is why operations show the net per trip.</p>
</div>

{{-- ===================== BUDGET VS ACTUAL ===================== --}}
<div class="tab-pane fade" id="tab-budget">
    <p class="lg-why">The layout of the “slide AG” sheet from the 2020 workbook: a forecast against what happened, ready for the general assembly. Forecast values below are the 2021 forecast, as an example.</p>
    <div class="card dc-card"><div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Line</th><th class="lg-num">Forecast in</th><th class="lg-num">Forecast out</th><th class="lg-num">Actual in</th><th class="lg-num">Actual out</th><th style="width:14rem">Used</th></tr></thead>
        <tbody>
        @foreach([
            ['Cotisations', 4400, null, 2143, null], ['Subsides', 4471.05, null, 3000, null], ['Matériel', null, 2000, null, 1151],
            ['Fosses (pool)', null, 2000, null, 0], ['Steinfort', null, 900, null, 843], ['AG', null, 0, null, 234],
            ['Licences moniteurs', null, 476, null, 0], ['Assurances', null, 160, null, 299], ['Frais de fonctionnement', null, 400, null, 28],
        ] as [$line, $fi, $fo, $ai, $ao])
            @php $f = $fi ?? $fo; $a = $ai ?? $ao; $pct = $f ? min(140, round($a / $f * 100)) : null; @endphp
            <tr><td>{{ $line }}</td><td class="lg-num">{{ $fi ? number_format($fi, 0, ',', ' ') : '' }}</td><td class="lg-num">{{ $fo ? number_format($fo, 0, ',', ' ') : '' }}</td>
                <td class="lg-num lg-in">{{ $ai ? number_format($ai, 0, ',', ' ') : '' }}</td><td class="lg-num lg-out">{{ $ao ? number_format($ao, 0, ',', ' ') : '' }}</td>
                <td>@if($pct !== null)<div class="lg-bar"><span style="width:{{ min(100, $pct) }}%; background:{{ $pct > 100 ? '#dc3545' : ($pct > 85 ? '#e0a800' : '#146c43') }}"></span></div><span class="lg-why">{{ $pct }}%</span>@endif</td></tr>
        @endforeach
        <tr><td>Sorties (trips, net)</td><td class="lg-num" colspan="2">forecast −3 500</td><td class="lg-num" colspan="2">actual +9 207 so far (44 483 in, 35 276 out)</td><td><span class="lg-why">trips still open</span></td></tr>
        </tbody>
    </table></div></div>
</div>

{{-- ===================== AUDIT ===================== --}}
<div class="tab-pane fade" id="tab-audit">
    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card dc-card mb-3"><div class="card-header">Bank statements reconciled</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Statement</th><th class="lg-num">Lines</th><th class="lg-num">Opening</th><th class="lg-num">In</th><th class="lg-num">Out</th><th class="lg-num">Closing</th><th></th></tr></thead>
                    <tbody>
                    @foreach($statements as [$n, $lines, $open, $in, $out, $close])
                        <tr><td>{{ $n }} <span class="text-muted small">({{ ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep'][$n - 1] }})</span></td><td class="lg-num">{{ $lines }}</td><td class="lg-num">{{ $eur($open) }}</td><td class="lg-num lg-in">{{ $eur($in) }}</td><td class="lg-num lg-out">{{ $eur($out) }}</td><td class="lg-num">{{ $eur($close) }}</td>
                            <td class="lg-in" title="{{ $eur($open) }} + {{ $eur($in) }} − {{ $eur($out) }} = {{ $eur($close) }}">✓</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
                <div class="card-footer small text-muted">Same check as the “Solde début …” lines of the old workbook, done automatically on import. A missing, edited or duplicated line breaks it.</div>
            </div>

            <div class="card dc-card"><div class="card-header">Findings the reviewers will see</div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between"><span>⚠ <strong>29 expenses without a bon à payer</strong> — 9 reimbursements to members, 6 trip payments, 14 others</span><a href="#" class="text-nowrap">Show</a></li>
                    <li class="list-group-item d-flex justify-content-between"><span>⚠ <strong>15 lines still unknown</strong> in the inbox</span><a href="#" class="text-nowrap">Show</a></li>
                    <li class="list-group-item d-flex justify-content-between"><span>⚠ <strong>Juan-les-Pins does not balance</strong> (net −€4 276 against ≈ +€450 expected)</span><a href="#" class="text-nowrap">Open</a></li>
                    <li class="list-group-item d-flex justify-content-between"><span>ℹ <strong>2 amounts differ from what was expected</strong> (top-up of €5.00, membership of €186.20)</span><a href="#" class="text-nowrap">Show</a></li>
                    <li class="list-group-item d-flex justify-content-between"><span>ℹ <strong>Cap Vert is open</strong> — settles after the trip in October</span><a href="#" class="text-nowrap">Open</a></li>
                    <li class="list-group-item text-success">✓ Closed loops all net to zero (2 of 2) · ✓ Bank balance check passes on every statement</li>
                </ul>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card dc-card mb-3"><div class="card-header">Evidence for a line</div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><strong>Recette communale de Steinfort · −272,71</strong><span class="lg-in">✓ complete</span></div>
                    <ol class="mb-0 mt-2 ps-3">
                        <li>Bank line, statement 1 (08/01/2026) — imported, balance-checked</li>
                        <li>Invoice 1844 / PI2025020083 — document library</li>
                        <li>Bon à payer <span class="lg-chip">260106 BaP Steinfort.pdf</span> — signed by the president</li>
                        <li>Category 52 · cost centre “Pool rental” · confirmed by the treasurer, 12/01</li>
                    </ol>
                    <div class="lg-why mt-2">One bon à payer can cover several lines, like the six mileage refunds that shared one file in 2022.</div>
                </div>
            </div>

            <div class="card dc-card mb-3"><div class="card-header">Review &amp; sign-off</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2"><span>Financial reviewer 1</span><span class="lg-pill lg-confirm">Waiting</span></div>
                    <div class="d-flex justify-content-between align-items-center mb-2"><span>Financial reviewer 2</span><span class="lg-pill lg-confirm">Waiting</span></div>
                    <div class="lg-why mb-3">Reviewers can read everything, comment on a line and flag it. They cannot edit the ledger.</div>
                    <button class="btn btn-sm btn-outline-secondary" type="button" disabled>Lock the period (after both sign)</button>
                </div>
            </div>

            <div class="card dc-card"><div class="card-header">Reports</div>
                <div class="list-group list-group-flush small">
                    <a href="#" class="list-group-item list-group-item-action">Tableau Compta 2026 — same workbook as 2020–2022 (Comptes + Rubriques)</a>
                    <a href="#" class="list-group-item list-group-item-action">Slide AG — budget vs actual</a>
                    <a href="#" class="list-group-item list-group-item-action">Reviewer pack (PDF): findings, statements, bons à payer index</a>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
</x-admin-layout>
