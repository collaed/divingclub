{{-- FLASSA licence card — professional horizontal credit card format --}}
@php $d = $user->detail; @endphp
<div style="width:85.60mm;height:53.98mm;background:#fff;border:.5px solid #aaa;border-radius:3.18mm;box-shadow:0 4px 10px rgba(0,0,0,.15);display:flex;flex-direction:column;padding:4mm;box-sizing:border-box;font-family:Helvetica,Arial,sans-serif;color:#1a1a1a;position:relative;overflow:hidden">

    <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:.4mm solid #000;padding-bottom:2mm;margin-bottom:3mm">
        <div style="font-size:2.2mm;font-weight:800;text-transform:uppercase;line-height:1.2;width:65%">
            Fédération Luxembourgeoise des Activités<br>et Sports Sub-Aquatiques
        </div>
        <div style="font-size:5mm;font-weight:900;text-transform:uppercase">Licence</div>
    </div>

    <div style="display:flex;flex-grow:1">
        <div style="width:22mm;display:flex;justify-content:center;align-items:center">
            <div style="width:20mm;height:20mm;border:.2mm solid #333;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;font-size:1.2mm;padding:1mm">
                <b style="font-size:3.5mm;display:block;margin-bottom:.5mm">FLASSA</b>
                LUXEMBOURG
            </div>
        </div>

        <div style="flex-grow:1;padding-left:4mm;display:flex;flex-direction:column;justify-content:space-between">
            <div style="margin-bottom:1.5mm">
                <span style="font-size:1.8mm;text-transform:uppercase;color:#666;display:block;margin-bottom:.3mm">Numéro de Licence</span>
                <span style="font-size:4mm;font-weight:bold;letter-spacing:.2mm">{{ $licence->licence_number }}</span>
            </div>
            <div style="margin-bottom:1.5mm">
                <span style="font-size:1.8mm;text-transform:uppercase;color:#666;display:block;margin-bottom:.3mm">Club Affilié</span>
                <span style="font-size:3mm;font-weight:bold">{{ config('club.full_name', 'Club Européen de Plongée') }}</span>
            </div>
            <div style="margin-bottom:1.5mm">
                <span style="font-size:1.8mm;text-transform:uppercase;color:#666;display:block;margin-bottom:.3mm">Titulaire &amp; Né(e) le</span>
                <span style="font-size:3mm;font-weight:bold">{{ $d->last_name }} {{ $d->first_name }} — {{ $d->date_of_birth?->format('d.m.Y') }}</span>
            </div>
            <div style="margin-bottom:1.5mm">
                <span style="font-size:1.8mm;text-transform:uppercase;color:#666;display:block;margin-bottom:.3mm">Adresse</span>
                <span style="font-size:2.2mm;font-weight:normal">{{ $d->address_line1 }} {{ $d->postal_code }} {{ $d->city }}</span>
            </div>
        </div>
    </div>

    <div style="margin-top:auto;text-align:center;border-top:.1mm solid #ddd;padding-top:1.5mm;display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:1.8mm;font-style:italic;color:#333;line-height:1.1">Licence basée sur certificat médical / Medical certificate based license</span>
        @if($pdfDoc)
            <a href="{{ route('profile.document.download', $pdfDoc) }}" style="font-size:2mm;color:#005696;text-decoration:none;border:.2mm solid #005696;border-radius:1.5mm;padding:.8mm 2mm;font-weight:600;white-space:nowrap">📄 PDF</a>
        @endif
    </div>
</div>
