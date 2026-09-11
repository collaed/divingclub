{{-- FFESSM licence card — credit card format (vertical), aligned to the
     official FFESSM licence card artwork: diagonal blue/teal stripe band at
     the top, federation badge, "LICENCE" in blue, licence number + name, QR
     code, insurance disclaimer. Graphics are kept compact so nothing here
     competes with the text below for space. --}}
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
<div style="width:53.98mm;height:85.60mm;background:#fff;border:.5px solid #aaa;border-radius:3.18mm;box-shadow:0 4px 10px rgba(0,0,0,.15);display:flex;flex-direction:column;align-items:center;padding:2.5mm;box-sizing:border-box;position:relative;overflow:hidden;font-family:Helvetica,Arial,sans-serif;color:#1a1a1a">
    {{-- Diagonal stripe band — top portion only, like the official card --}}
    <div style="position:absolute;top:0;left:0;right:0;height:20mm;z-index:0;background:
        linear-gradient(128deg,
            #062339 0%, #062339 8%,
            #0e2f52 8%, #0e2f52 16%,
            #0c1a26 16%, #0c1a26 20%,
            #0f5c86 20%, #0f5c86 32%,
            #17a2c9 32%, #17a2c9 46%,
            #0c1a26 46%, #0c1a26 50%,
            #1478a8 50%, #1478a8 62%,
            #0e2f52 62%, #0e2f52 72%,
            #ffffff 72%, #ffffff 100%
        )"></div>

    {{-- Federation badge — the artwork already bundles logo + wordmark + tagline --}}
    <div style="margin-top:2.5mm;z-index:2;width:19mm;height:19mm;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;padding:1.5mm;box-sizing:border-box">
        <img src="/images/logos/ffessm.png" alt="FFESSM" style="width:100%;height:100%;object-fit:contain">
    </div>

    <div style="margin-top:3mm;text-align:center;z-index:2;width:100%">
        <div style="color:#005696;font-size:4.8mm;font-weight:800;letter-spacing:.4mm;margin-bottom:1.5mm">LICENCE</div>
        <div style="font-size:3mm;font-weight:800;margin:.8mm 0">N° {{ $licence->licence_number }}</div>
        <div style="font-size:2.8mm;font-weight:800;text-transform:uppercase;margin-bottom:2.5mm">{{ $d->first_name }} {{ $d->last_name }}</div>
        @if($qrBase64)
            <img src="data:image/png;base64,{{ $qrBase64 }}" alt="QR" style="width:15mm;height:15mm">
        @else
            <div style="width:15mm;height:15mm;background:#f9f9f9;border:.1mm solid #ccc;display:flex;justify-content:center;align-items:center;font-size:1.4mm;color:#999;margin:0 auto">{{ __('No QR key') }}</div>
        @endif
    </div>
    <div style="margin-top:auto;padding-top:1.5mm;font-size:1.5mm;color:#005696;font-weight:700;text-align:center;line-height:1.3;z-index:2;width:100%">
        <p style="margin:0">Assurance en RC y compris pour la pêche sous-marine à partir de 16 ans.</p>
        <p style="margin:0">The present licence covers your personal liability worldwide in case you would cause damages to third parties.</p>
    </div>
</div>
