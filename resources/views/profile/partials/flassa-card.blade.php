{{-- FLASSA licence card — mirrors the official FLASSA licence PDF layout
     (logo + federation name top, "Licence <number>" row, centered
     club/holder/address block, bold disclaimer band at the bottom). --}}
@php $d = $user->detail; $theme = App\Services\ThemeService::settings(); @endphp
<div style="width:54mm;height:85.60mm;background:#fff;border:.5px solid #aaa;border-radius:2mm;box-shadow:0 4px 10px rgba(0,0,0,.15);display:flex;flex-direction:column;padding:3mm;box-sizing:border-box;font-family:Arial,Helvetica,sans-serif;color:#000;position:relative;overflow:hidden">

    @if($pdfDoc)
        <a href="{{ route('profile.document.download', $pdfDoc) }}" title="{{ __('Download PDF') }}" style="position:absolute;top:1.5mm;right:1.5mm;font-size:1.6mm;color:#005696;text-decoration:none;border:.2mm solid #005696;border-radius:1.5mm;padding:.6mm 1.2mm;font-weight:600;background:#fff;z-index:2">📄 PDF</a>
    @endif

    {{-- Logo + federation name, side by side like the PDF header --}}
    <div style="display:flex;align-items:flex-start;gap:2mm">
        <img src="/images/logos/flassa.png" alt="FLASSA" style="width:13mm;height:13mm;object-fit:contain;flex-shrink:0">
        <div style="font-size:2.1mm;line-height:1.25;margin-top:.5mm">
            Fédération Luxembourgeoise des Activités et Sports Sub-Aquatiques
        </div>
    </div>

    {{-- "Licence" + number, same row as the PDF --}}
    <div style="display:flex;justify-content:space-between;align-items:baseline;margin-top:3mm">
        <div style="font-size:4mm;font-weight:800">Licence</div>
        <div style="font-size:3.2mm;font-weight:800;white-space:nowrap">{{ $licence->season ? substr($licence->season, 0, 4) : '' }}{{ $licence->licence_number }}</div>
    </div>

    {{-- Club / holder / address, centered like the PDF --}}
    <div style="text-align:center;margin-top:2.5mm;font-size:2.3mm;line-height:1.5">
        <div>{{ strtoupper($theme['club_full_name'] ?? 'Club Européen de Plongée') }}</div>
        <div>{{ $d->last_name }} {{ $d->first_name }} - {{ $d->date_of_birth?->format('d.m.Y') }}</div>
        <div>{{ $d->address_line1 }} {{ $d->postal_code ? 'L-' . $d->postal_code : '' }} {{ strtoupper($d->city ?? '') }}</div>
    </div>

    {{-- Bold disclaimer band, as in the PDF --}}
    <div style="margin-top:auto;text-align:center;border-top:.2mm solid #000;padding-top:1.5mm">
        <span style="font-size:1.9mm;font-weight:800;font-stretch:condensed;font-family:'Arial Narrow',Arial,sans-serif;letter-spacing:.1mm">Licence basée sur certificat médical / Medical certificate based license</span>
    </div>
</div>
