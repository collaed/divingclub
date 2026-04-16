{{-- FLASSA licence card — HTML rendered, with PDF download link --}}
@php $d = $user->detail; @endphp
<div style="width:85.60mm;height:53.98mm;background:linear-gradient(135deg,#004d1a 0%,#006622 50%,#008833 100%);border-radius:3.18mm;box-shadow:0 4px 10px rgba(0,0,0,.3);display:flex;align-items:center;padding:4mm;box-sizing:border-box;position:relative;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#fff">
    {{-- Left: logo + text --}}
    <div style="flex:1;z-index:2">
        <div style="font-weight:900;font-size:5mm;letter-spacing:.3mm">FLASSA</div>
        <div style="font-size:1.5mm;opacity:.8;margin-top:1mm">Fédération Luxembourgeoise des<br>Activités Subaquatiques et de Sauvetage</div>
        <div style="margin-top:3mm;font-size:3mm;font-weight:bold;text-transform:uppercase">{{ $d->last_name }}</div>
        <div style="font-size:2.5mm;text-transform:uppercase;opacity:.9">{{ $d->first_name }}</div>
        <div style="margin-top:2mm;font-size:2.5mm;font-weight:bold">{{ $licence->licence_number }}</div>
        <div style="font-size:1.8mm;opacity:.7">{{ $licence->season }}</div>
    </div>
    {{-- Right: download button --}}
    <div style="z-index:2;text-align:center">
        @if($pdfDoc)
            <a href="{{ route('profile.document.download', $pdfDoc) }}" style="display:inline-block;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);border-radius:2mm;padding:2mm 3mm;color:#fff;text-decoration:none;font-size:2mm;font-weight:bold" title="{{ __('Download PDF') }}">📄 PDF</a>
        @endif
    </div>
    {{-- Decoration --}}
    <div style="position:absolute;right:-8mm;top:-8mm;width:30mm;height:30mm;border-radius:50%;background:rgba(255,255,255,.06);z-index:1"></div>
    <div style="position:absolute;right:2mm;bottom:-5mm;width:20mm;height:20mm;border-radius:50%;background:rgba(255,255,255,.04);z-index:1"></div>
</div>
