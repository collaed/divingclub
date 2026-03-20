{{-- Fiche de Sécurité PDF — printable dive safety sheet per FFESSM format | ClubCEP.eu --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fiche de Sécurité — {{ $event->title }} — {{ $event->event_date->format('d/m/Y') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; margin: 15px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        h2 { font-size: 12px; margin: 10px 0 4px; border-bottom: 1px solid #333; padding-bottom: 2px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 10px; }
        .header-left { width: 60%; }
        .header-right { width: 38%; text-align: right; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #999; padding: 3px 5px; text-align: left; }
        th { background: #e9ecef; font-size: 9px; text-transform: uppercase; }
        .group-header { background: #d1ecf1; font-weight: bold; font-size: 11px; }
        .leader { font-weight: bold; }
        .rank-cell { text-align: center; font-size: 9px; }
        .mode-supervised { color: #0d6efd; }
        .mode-autonomous { color: #198754; }
        .mode-training { color: #ffc107; }
        .mode-certification { color: #dc3545; }
        .footer { margin-top: 15px; font-size: 8px; color: #666; border-top: 1px solid #ccc; padding-top: 4px; }
        .signatures { margin-top: 20px; }
        .signatures td { border: none; height: 40px; vertical-align: bottom; border-bottom: 1px solid #333; width: 33%; }
        .emergency { background: #fff3cd; padding: 6px; margin-top: 10px; border: 1px solid #ffc107; }
    </style>
</head>
<body>
    {{-- Header --}}
    <table style="border:none; margin-bottom:10px;">
        <tr style="border:none;">
            <td style="border:none; width:60%;">
                <h1>FICHE DE SÉCURITÉ</h1>
                <strong>{{ $event->title }}</strong><br>
                {{ $event->event_date->translatedFormat('l d F Y') }}
                @if($event->start_time) — {{ \Carbon\Carbon::parse($event->start_time)->format('H:i') }}@endif
            </td>
            <td style="border:none; width:40%; text-align:right; font-size:9px;">
                <strong>{{ \App\Models\ThemeSetting::get('club_full_name', config('app.name')) }}</strong><br>
                {{ parse_url(config('app.url'), PHP_URL_HOST) ?: config('app.url') }}<br>
                @if($event->diveSite)
                    📍 {{ $event->diveSite->name }}<br>
                    @if($event->diveSite->max_depth)
                        Prof. max site: {{ $event->diveSite->max_depth }}m
                    @endif
                @endif
                <br>Généré le {{ now()->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>

    {{-- Dive groups table --}}
    <table>
        <thead>
            <tr>
                <th style="width:5%">Pal.</th>
                <th style="width:8%">Mode</th>
                <th style="width:6%">Prof.</th>
                <th style="width:5%">Rôle</th>
                <th style="width:22%">Nom</th>
                <th style="width:12%">Brevet</th>
                <th style="width:10%">Fédération</th>
                <th style="width:10%">N° Licence</th>
                <th style="width:10%">Aptitude</th>
                <th style="width:12%">Observations</th>
            </tr>
        </thead>
        <tbody>
            @foreach($event->diveGroups as $group)
                @php $memberCount = $group->members->count(); @endphp
                @foreach($group->members->sortByDesc('role') as $i => $m)
                    @php
                        $cert = $m->user->certificationLevels->where('category', '!=', 'specialty')->sortByDesc('rank')->first();
                        $licence = $m->user->licences->first();
                    @endphp
                    <tr>
                        @if($i === 0)
                            <td rowspan="{{ $memberCount }}" style="text-align:center; vertical-align:middle; font-weight:bold; font-size:12px;">{{ $loop->parent->iteration }}</td>
                            <td rowspan="{{ $memberCount }}" class="rank-cell mode-{{ $group->dive_mode }}" style="vertical-align:middle;">{{ ucfirst($group->dive_mode) }}</td>
                            <td rowspan="{{ $memberCount }}" style="text-align:center; vertical-align:middle; font-weight:bold;">{{ $group->planned_depth ? $group->planned_depth . 'm' : '—' }}</td>
                        @endif
                        <td class="rank-cell">{{ $m->role === 'leader' ? '👑 GP' : '🤿' }}</td>
                        <td class="{{ $m->role === 'leader' ? 'leader' : '' }}">{{ $m->user->detail?->last_name }} {{ $m->user->detail?->first_name }}</td>
                        <td class="rank-cell">{{ $cert?->code ?? '—' }}</td>
                        <td class="rank-cell">{{ $cert?->federation?->acronym ?? '—' }}</td>
                        <td class="rank-cell">{{ $licence?->licence_number ?? '—' }}</td>
                        <td class="rank-cell">{{ $cert?->code ?? '—' }}</td>
                        <td></td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    {{-- Unassigned participants (if any) --}}
    @php
        $assignedIds = $event->diveGroups->flatMap(fn($g) => $g->members->pluck('user_id'))->toArray();
        $unassignedRegs = $event->registrations->where('status', 'confirmed')->filter(fn($r) => !in_array($r->user_id, $assignedIds));
    @endphp
    @if($unassignedRegs->count())
        <div class="emergency">
            <strong>⚠️ Plongeurs non affectés ({{ $unassignedRegs->count() }}):</strong>
            {{ $unassignedRegs->map(fn($r) => $r->user->detail?->first_name . ' ' . $r->user->detail?->last_name)->join(', ') }}
        </div>
    @endif

    {{-- Emergency info --}}
    <div class="emergency">
        <strong>📞 Urgences:</strong> 112 | Caisson hyperbare: +352 4411-3091 (CHL) |
        Directeur de plongée: ___________________________
    </div>

    {{-- Signatures --}}
    <div class="signatures">
        <table>
            <tr>
                <td>Directeur de plongée:</td>
                <td>Responsable sécurité surface:</td>
                <td>Date & heure:</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        {{ \App\Models\ThemeSetting::get('club_full_name', config('app.name')) }} — Fiche de sécurité générée automatiquement. Vérifiez les aptitudes avant immersion.
    </div>
</body>
</html>
