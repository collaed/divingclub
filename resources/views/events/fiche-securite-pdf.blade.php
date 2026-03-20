{{-- Fiche de Sécurité PDF — FFESSM 2024-2025 format | ClubCEP.eu
     Printable dive safety sheet: 4 palanquée rows max (12-16 divers),
     full emergency info block with hospital, hyperbaric chamber, required
     equipment, and VHF/phone contacts from the dive site record.
     Watermarked when installation is unlicensed. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fiche de Sécurité — {{ $event->title }} — {{ $event->event_date->format('d/m/Y') }}</title>
    <style>
        @page { margin: 12mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; margin: 0; color: #222; }
        h1 { font-size: 15px; margin: 0; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #666; padding: 2px 4px; }
        th { background: #d6d6d6; font-size: 8px; text-transform: uppercase; text-align: center; }

        /* Header layout */
        .header-table td { border: none; vertical-align: top; }
        .site-info { font-size: 8px; color: #444; }

        /* Palanquée table */
        .pal-num { text-align: center; font-weight: bold; font-size: 13px; vertical-align: middle; }
        .pal-mode { text-align: center; font-size: 8px; vertical-align: middle; }
        .pal-depth { text-align: center; font-weight: bold; vertical-align: middle; }
        .leader { font-weight: bold; }
        .center { text-align: center; }
        .mode-supervised { color: #0d6efd; }
        .mode-autonomous { color: #198754; }
        .mode-training { color: #856404; }
        .mode-certification { color: #dc3545; }

        /* Emergency block */
        .emergency-block { margin-top: 8px; border: 2px solid #dc3545; padding: 6px; background: #fff8f8; }
        .emergency-block h2 { font-size: 11px; margin: 0 0 4px; color: #dc3545; }
        .emergency-block table td { border: none; padding: 1px 4px; font-size: 9px; }
        .emergency-block .label { font-weight: bold; width: 35%; color: #555; }

        /* Equipment block */
        .equip-block { margin-top: 6px; border: 1px solid #ffc107; padding: 5px; background: #fffdf0; }
        .equip-block h2 { font-size: 10px; margin: 0 0 3px; color: #856404; }

        /* Signatures */
        .sig-table { margin-top: 12px; }
        .sig-table td { border: none; height: 35px; vertical-align: bottom; border-bottom: 1px solid #333; width: 33%; font-size: 9px; }

        /* Unassigned warning */
        .unassigned { margin-top: 6px; padding: 4px 6px; background: #fff3cd; border: 1px solid #ffc107; font-size: 9px; }

        /* Footer */
        .footer { margin-top: 8px; font-size: 7px; color: #999; border-top: 1px solid #ccc; padding-top: 3px; }

        /* License watermark */
        .watermark { position: fixed; top: 40%; left: 10%; font-size: 50px; color: rgba(220,53,69,0.12); transform: rotate(-30deg); z-index: -1; white-space: nowrap; font-weight: bold; }
    </style>
</head>
<body>
    {{-- License watermark (only shown on unlicensed installations) --}}
    @if($licenseWatermark ?? null)
        <div class="watermark">{{ $licenseWatermark }}</div>
    @endif

    @php
        $site = $event->diveSite;
        $clubName = \App\Models\ThemeSetting::get('club_full_name', config('app.name'));
        $groups = $event->diveGroups->take(4); // Max 4 palanquées per fiche
    @endphp

    {{-- ═══════════ HEADER ═══════════ --}}
    <table class="header-table" style="margin-bottom:8px;">
        <tr>
            <td style="width:55%;">
                <h1>FICHE DE SÉCURITÉ</h1>
                <strong>{{ $clubName }}</strong><br>
                <span style="font-size:10px;">{{ $event->title }}</span><br>
                {{ $event->event_date->translatedFormat('l d F Y') }}
                @if($event->start_time) — {{ \Carbon\Carbon::parse($event->start_time)->format('H:i') }}@endif
            </td>
            <td style="width:45%; text-align:right;" class="site-info">
                @if($site)
                    <strong>📍 {{ $site->name }}</strong><br>
                    @if($site->region) {{ $site->region }}, @endif{{ $site->country }}<br>
                    @if($site->water_type) Type: {{ ucfirst($site->water_type) }} @endif
                    @if($site->max_depth) · Prof. max site: {{ $site->max_depth }}m @endif<br>
                    @if($site->latitude && $site->longitude)
                        GPS: {{ number_format($site->latitude, 5) }}, {{ number_format($site->longitude, 5) }}<br>
                    @endif
                @endif
                Directeur de plongée: ____________________<br>
                Généré le {{ now()->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>

    {{-- ═══════════ PALANQUÉES (max 4 rows = 12-16 divers) ═══════════ --}}
    <table>
        <thead>
            <tr>
                <th style="width:4%">Pal</th>
                <th style="width:7%">Mode</th>
                <th style="width:5%">Prof</th>
                <th style="width:4%">Rôle</th>
                <th style="width:20%">Nom Prénom</th>
                <th style="width:9%">Brevet</th>
                <th style="width:8%">Féd.</th>
                <th style="width:10%">N° Licence</th>
                <th style="width:8%">Aptitude</th>
                <th style="width:5%">Cert. Méd.</th>
                <th style="width:6%">H. Imm.</th>
                <th style="width:6%">H. Sort.</th>
                <th style="width:8%">Obs.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($groups as $group)
                @php
                    $members = $group->members->sortByDesc('role');
                    $memberCount = $members->count();
                @endphp
                @foreach($members as $i => $m)
                    @php
                        $cert = $m->user->certificationLevels->where('category', '!=', 'specialty')->sortByDesc('rank')->first();
                        $licence = $m->user->licences->first();
                        $medStatus = app(\App\Services\MedicalComplianceService::class)->getStatus($m->user);
                    @endphp
                    <tr>
                        @if($i === 0)
                            <td rowspan="{{ $memberCount }}" class="pal-num">{{ $loop->parent->iteration }}</td>
                            <td rowspan="{{ $memberCount }}" class="pal-mode mode-{{ $group->dive_mode }}">{{ ucfirst($group->dive_mode) }}</td>
                            <td rowspan="{{ $memberCount }}" class="pal-depth">{{ $group->planned_depth ? $group->planned_depth . 'm' : '—' }}</td>
                        @endif
                        <td class="center">{{ $m->role === 'leader' ? 'GP' : '' }}</td>
                        <td class="{{ $m->role === 'leader' ? 'leader' : '' }}">{{ $m->user->detail?->last_name }} {{ $m->user->detail?->first_name }}</td>
                        <td class="center">{{ $cert?->code ?? '—' }}</td>
                        <td class="center">{{ $cert?->federation?->acronym ?? '—' }}</td>
                        <td class="center">{{ $licence?->licence_number ?? '—' }}</td>
                        <td class="center">{{ $cert?->code ?? '—' }}</td>
                        <td class="center">
                            @if($medStatus['status'] === 'compliant') ✓
                            @elseif($medStatus['status'] === 'expiring') ⚠
                            @else ✗
                            @endif
                        </td>
                        <td></td>{{-- H. Immersion — filled by hand --}}
                        <td></td>{{-- H. Sortie — filled by hand --}}
                        <td></td>{{-- Observations — filled by hand --}}
                    </tr>
                @endforeach
            @endforeach

            {{-- Empty rows to fill 4 palanquées if fewer groups exist --}}
            @for($p = $groups->count(); $p < 4; $p++)
                @for($r = 0; $r < 3; $r++)
                    <tr>
                        @if($r === 0)
                            <td rowspan="3" class="pal-num" style="color:#ccc;">{{ $p + 1 }}</td>
                            <td rowspan="3"></td>
                            <td rowspan="3"></td>
                        @endif
                        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                    </tr>
                @endfor
            @endfor
        </tbody>
    </table>

    {{-- ═══════════ UNASSIGNED PARTICIPANTS ═══════════ --}}
    @php
        $assignedIds = $event->diveGroups->flatMap(fn($g) => $g->members->pluck('user_id'))->toArray();
        $unassigned = $event->registrations->where('status', 'confirmed')->filter(fn($r) => !in_array($r->user_id, $assignedIds));
    @endphp
    @if($unassigned->count())
        <div class="unassigned">
            <strong>⚠️ Plongeurs non affectés ({{ $unassigned->count() }}):</strong>
            {{ $unassigned->map(fn($r) => $r->user->detail?->first_name . ' ' . $r->user->detail?->last_name)->join(', ') }}
        </div>
    @endif

    {{-- ═══════════ EMERGENCY INFO ═══════════ --}}
    <div class="emergency-block">
        <h2>🚨 INFORMATIONS D'URGENCE</h2>
        <table>
            <tr>
                <td class="label">📞 Secours / SAMU:</td>
                <td>{{ $site?->emergency_phone ?? '112' }}</td>
                <td class="label">📻 VHF:</td>
                <td>{{ $site?->vhf_channel ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">🏥 Hôpital le plus proche:</td>
                <td colspan="3">{{ $site?->nearest_hospital ?? '—' }}@if($site?->hospital_distance_km) (≈{{ $site->hospital_distance_km }}km)@endif</td>
            </tr>
            <tr>
                <td class="label">🫁 Caisson hyperbare:</td>
                <td colspan="3">
                    {{ $site?->nearest_hyperbaric_chamber ?? '—' }}
                    @if($site?->hyperbaric_phone) · ☎ {{ $site->hyperbaric_phone }}@endif
                    @if($site?->hyperbaric_distance_km) (≈{{ $site->hyperbaric_distance_km }}km)@endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ═══════════ REQUIRED SAFETY EQUIPMENT ═══════════ --}}
    @if($site?->required_safety_equipment)
        <div class="equip-block">
            <h2>🧰 MATÉRIEL DE SÉCURITÉ OBLIGATOIRE</h2>
            {{ $site->required_safety_equipment }}
        </div>
    @endif

    {{-- ═══════════ SIGNATURES ═══════════ --}}
    <table class="sig-table">
        <tr>
            <td>Directeur de plongée:</td>
            <td>Responsable sécurité surface:</td>
            <td>Date & heure de début:</td>
        </tr>
    </table>

    {{-- ═══════════ FOOTER ═══════════ --}}
    <div class="footer">
        {{ $clubName }} — Fiche de sécurité générée automatiquement. Vérifiez les aptitudes et certificats médicaux avant immersion.
        @if($site?->safety_notes) · ⚠ {{ $site->safety_notes }}@endif
    </div>
</body>
</html>
