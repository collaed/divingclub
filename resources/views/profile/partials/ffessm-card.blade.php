{{-- FFESSM licence card — rendered inline, credit card format (vertical) --}}
@php $d = $user->detail; @endphp
<div style="width:53.98mm;height:85.60mm;background:#fff;border-radius:3.18mm;box-shadow:0 4px 10px rgba(0,0,0,.3);display:flex;flex-direction:column;align-items:center;padding:3mm;box-sizing:border-box;position:relative;overflow:hidden;font-family:Arial,Helvetica,sans-serif">
    <div style="position:absolute;width:40mm;height:40mm;background:linear-gradient(135deg,rgba(0,174,239,.2),rgba(0,86,150,.1));border-radius:50%;top:15mm;left:-10mm;z-index:1"></div>
    <div style="margin-top:2mm;z-index:2">
        <div style="width:25mm;height:25mm;border-radius:50%;border:.2mm solid #ddd;display:flex;flex-direction:column;justify-content:center;align-items:center;background:#fff">
            <p style="color:#005696;font-weight:900;font-size:5mm;margin:0;line-height:1">FFESSM</p>
            <p style="font-size:1.2mm;color:#005696;text-align:center;text-transform:uppercase;margin-top:1mm">Fédération Française<br>Études &amp; Sports Sous-Marins</p>
        </div>
    </div>
    <div style="margin-top:4mm;text-align:center;z-index:2;width:100%">
        <div style="color:#005696;font-size:6mm;font-weight:bold;letter-spacing:.5mm;margin-bottom:2mm">LICENCE</div>
        <div style="font-size:3.5mm;font-weight:bold;color:#000;margin:1mm 0">N° {{ $licence->licence_number }}</div>
        <div style="font-size:3.2mm;font-weight:bold;color:#000;text-transform:uppercase;margin-bottom:4mm">{{ $d->last_name }} {{ $d->first_name }}</div>
        @if($licence->federation_key)
            <div style="margin:0 auto;width:18mm;height:18mm">
                <img src="{{ route('qr.federation', $licence) }}" alt="QR" style="width:18mm;height:18mm">
            </div>
        @else
            <div style="width:18mm;height:18mm;background:#f9f9f9;border:.1mm solid #ccc;display:flex;justify-content:center;align-items:center;font-size:1.5mm;color:#999;margin:0 auto">{{ __('No QR key') }}</div>
        @endif
    </div>
    <div style="margin-top:auto;font-size:1.5mm;color:#005696;font-weight:bold;text-align:center;line-height:1.2;z-index:2">
        <p style="margin:0">Assurance en RC y compris pour la pêche sous-marine à partir de 16 ans.</p>
        <p style="margin:0">The present licence covers your personal liability worldwide.</p>
    </div>
</div>
