{{-- FLASSA licence card — matches official card layout --}}
@php $d = $user->detail; $theme = App\Services\ThemeService::settings(); @endphp
<div style="width:85.60mm;height:53.98mm;background:#fff;border:.5px solid #aaa;border-radius:3.18mm;box-shadow:0 4px 10px rgba(0,0,0,.15);display:flex;flex-direction:column;padding:4mm;box-sizing:border-box;font-family:Helvetica,Arial,sans-serif;color:#1a1a1a;position:relative;overflow:hidden">

    <div style="display:flex;flex-grow:1;gap:3mm">
        {{-- Left: club + holder --}}
        <div style="flex:1;display:flex;flex-direction:column;justify-content:space-between">
            <div>
                <div style="font-size:2.5mm;font-weight:800;text-transform:uppercase;line-height:1.3">{{ $theme['club_full_name'] ?? 'Club Européen de Plongée' }}</div>
                <div style="font-size:2.8mm;font-weight:700;margin-top:2mm">{{ $d->last_name }} {{ $d->first_name }} — {{ $d->date_of_birth?->format('d.m.Y') }}</div>
                <div style="font-size:2mm;margin-top:1mm;color:#444">{{ $d->address_line1 }} {{ $d->postal_code ? 'L-' . $d->postal_code : '' }} {{ strtoupper($d->city ?? '') }}</div>
            </div>
            <div style="font-size:4mm;font-weight:900;letter-spacing:.3mm;margin-top:2mm">{{ $licence->season ? substr($licence->season, 0, 4) : '' }}{{ $licence->licence_number }}</div>
        </div>

        {{-- Right: federation --}}
        <div style="width:28mm;display:flex;flex-direction:column;align-items:center;justify-content:space-between;text-align:center">
            <div style="font-size:5mm;font-weight:900;text-transform:uppercase">Licence</div>
            <div style="font-size:1.8mm;font-weight:700;line-height:1.3;margin-top:1mm">
                Fédération Luxembourgeoise<br>des Activités et Sports<br>Sub-Aquatiques
            </div>
            @if($pdfDoc)
                <a href="{{ route('profile.document.download', $pdfDoc) }}" style="font-size:1.8mm;color:#005696;text-decoration:none;border:.2mm solid #005696;border-radius:1.5mm;padding:.8mm 2mm;font-weight:600;margin-top:2mm">📄 PDF</a>
            @endif
        </div>
    </div>

    <div style="margin-top:auto;text-align:center;border-top:.1mm solid #ddd;padding-top:1mm">
        <span style="font-size:1.6mm;font-style:italic;color:#555">Licence basée sur certificat médical / Medical certificate based license</span>
    </div>
</div>
