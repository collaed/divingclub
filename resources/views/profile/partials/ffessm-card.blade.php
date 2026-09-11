{{-- FFESSM licence card — credit card format (vertical), aligned to the
     official FFESSM licence card artwork: diagonal blue/teal stripe
     background, full federation badge in a white circle, "LICENCE" in blue,
     licence number + name, QR code, insurance disclaimer. --}}
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
    {{-- Diagonal stripe background, echoing the official card artwork --}}
    <div style="position:absolute;inset:0;z-index:0;background:
        linear-gradient(128deg,
            #062339 0%, #062339 6%,
            #0e2f52 6%, #0e2f52 12%,
            #0c1a26 12%, #0c1a26 16%,
            #0f5c86 16%, #0f5c86 24%,
            #17a2c9 24%, #17a2c9 34%,
            #0c1a26 34%, #0c1a26 37%,
            #1478a8 37%, #1478a8 46%,
            #0e2f52 46%, #0e2f52 52%,
            #ffffff 52%, #ffffff 100%
        )"></div>
    <div style="position:absolute;width:6mm;height:6mm;border-radius:50%;background:#0c1a26;top:6mm;left:6mm;z-index:1"></div>
    <div style="position:absolute;width:4mm;height:4mm;border-radius:50%;background:#17a2c9;top:13mm;left:3mm;z-index:1"></div>

    {{-- Federation badge — the artwork already bundles logo + wordmark + tagline --}}
    <div style="margin-top:5mm;z-index:2;width:28mm;height:28mm;border-radius:50%;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;padding:2mm;box-sizing:border-box">
        <img src="/images/logos/ffessm.png" alt="FFESSM" style="width:100%;height:100%;object-fit:contain">
    </div>

    <div style="margin-top:4mm;text-align:center;z-index:2;width:100%;background:#fff;border-radius:1mm">
        <div style="color:#005696;font-size:6mm;font-weight:800;letter-spacing:.5mm;margin-bottom:2mm">LICENCE</div>
        <div style="font-size:3.5mm;font-weight:800;margin:1mm 0">N° {{ $licence->licence_number }}</div>
        <div style="font-size:3.2mm;font-weight:800;text-transform:uppercase;margin-bottom:3mm">{{ $d->first_name }} {{ $d->last_name }}</div>
        @if($qrBase64)
            <img src="data:image/png;base64,{{ $qrBase64 }}" alt="QR" style="width:18mm;height:18mm">
        @else
            <div style="width:18mm;height:18mm;background:#f9f9f9;border:.1mm solid #ccc;display:flex;justify-content:center;align-items:center;font-size:1.5mm;color:#999;margin:0 auto">{{ __('No QR key') }}</div>
        @endif
    </div>
    <div style="margin-top:auto;font-size:1.6mm;color:#005696;font-weight:700;text-align:center;line-height:1.3;z-index:2;background:#fff;width:100%">
        <p style="margin:0">Assurance en RC y compris pour la pêche sous-marine à partir de 16 ans.</p>
        <p style="margin:0">The present licence covers your personal liability worldwide in case you would cause damages to third parties.</p>
    </div>
</div>
