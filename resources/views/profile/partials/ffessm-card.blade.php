{{-- FFESSM licence card — credit card format (vertical) with inline QR --}}
@php
    $d = $user->detail;
    $qrBase64 = null;
    if ($licence->federation_key) {
        $number = preg_replace('/^[A-Z]-\d{2}-/', '', $licence->licence_number);
        $url = "https://infolicencie.ffessm.fr/Home/InfoLicence?number={$number}&key={$licence->federation_key}";
        try {
            $qrPng = \Endroid\QrCode\Builder\Builder::create()
                ->writer(new \Endroid\QrCode\Writer\PngWriter())
                ->data($url)
                ->size(150)
                ->build()
                ->getString();
            $qrBase64 = base64_encode($qrPng);
        } catch (\Throwable) {}
    }
@endphp
<div style="width:53.98mm;height:85.60mm;background:#fff;border:.5px solid #aaa;border-radius:3.18mm;box-shadow:0 4px 10px rgba(0,0,0,.15);display:flex;flex-direction:column;align-items:center;padding:3mm;box-sizing:border-box;position:relative;overflow:hidden;font-family:Helvetica,Arial,sans-serif;color:#1a1a1a">
    <div style="position:absolute;width:40mm;height:40mm;background:linear-gradient(135deg,rgba(0,174,239,.2),rgba(0,86,150,.1));border-radius:50%;top:15mm;left:-10mm;z-index:1"></div>
    <div style="margin-top:2mm;z-index:2">
        <div style="width:25mm;height:25mm;border-radius:50%;border:.2mm solid #ddd;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;font-size:1.2mm;padding:1mm;background:#fff">
            <b style="font-size:3.5mm;display:block;margin-bottom:.5mm;color:#005696">FFESSM</b>
            <span style="font-size:1.2mm;color:#005696;text-transform:uppercase">Fédération Française<br>Études &amp; Sports Sous-Marins</span>
        </div>
    </div>
    <div style="margin-top:4mm;text-align:center;z-index:2;width:100%">
        <div style="color:#005696;font-size:6mm;font-weight:bold;letter-spacing:.5mm;margin-bottom:2mm">LICENCE</div>
        <div style="font-size:3.5mm;font-weight:bold;margin:1mm 0">N° {{ $licence->licence_number }}</div>
        <div style="font-size:3.2mm;font-weight:bold;text-transform:uppercase;margin-bottom:4mm">{{ $d->last_name }} {{ $d->first_name }}</div>
        @if($qrBase64)
            <img src="data:image/png;base64,{{ $qrBase64 }}" alt="QR" style="width:18mm;height:18mm">
        @else
            <div style="width:18mm;height:18mm;background:#f9f9f9;border:.1mm solid #ccc;display:flex;justify-content:center;align-items:center;font-size:1.5mm;color:#999;margin:0 auto">{{ __('No QR key') }}</div>
        @endif
    </div>
    <div style="margin-top:auto;font-size:1.5mm;color:#005696;font-weight:bold;text-align:center;line-height:1.2;z-index:2">
        <p style="margin:0">Assurance en RC y compris pour la pêche sous-marine à partir de 16 ans.</p>
        <p style="margin:0">The present licence covers your personal liability worldwide.</p>
    </div>
</div>
