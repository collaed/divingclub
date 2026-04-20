<?php

/**
 * QR code generation: vCard, SEPA EPC, federation licence, and signed payment URLs.
 *
 * Signed payment QRs encode a URL (not raw bank details) so the club's TLS
 * certificate proves identity and an HMAC signature prevents tampering.
 * This mitigates quishing attacks on EPC QR codes.
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Controllers;

use App\Models\MemberLicence;
use App\Models\PaymentExpected;
use App\Models\ThemeSetting;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QrCodeController extends Controller
{
    public function vcard(): \Symfony\Component\HttpFoundation\Response
    {
        $user = auth()->user();
        $d = $user->detail;

        $vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\n";
        $vcard .= "N:{$d?->last_name};{$d?->first_name}\r\n";
        $vcard .= "FN:{$user->name}\r\n";
        $vcard .= "EMAIL:{$user->primary_email}\r\n";
        if ($d?->phone_mobile) {
            $vcard .= "TEL;TYPE=CELL:{$d->phone_mobile}\r\n";
        }
        $vcard .= 'ORG:'.ThemeSetting::get('club_full_name', 'Diving Club')."\r\n";
        $vcard .= "END:VCARD\r\n";

        return $this->generatePng($vcard, "vcard-{$user->id}.png");
    }

    // ─── Signed Payment QR (anti-quishing) ─────────────────────

    /** Generate a QR containing a signed verification URL instead of raw EPC data. */
    public function signedPaymentQr(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $amount = round((float) $request->query('amount', 0), 2);
        $communication = $request->query('communication', '');

        if ($amount <= 0) {
            return response('Invalid amount', 400);
        }

        $url = self::buildSignedUrl($amount, $communication);

        return $this->generatePng($url, 'payment-qr.png', false);
    }

    /** Verification page — user lands here after scanning the QR. */
    public function verifyPayment(Request $request): View
    {
        $amount = (float) $request->query('a', 0);
        $communication = $request->query('c', '');
        $expires = (int) $request->query('e', 0);
        $signature = $request->query('s', '');

        // Verify signature
        $payload = $amount.'|'.$communication.'|'.$expires;
        $expected = hash_hmac('sha256', $payload, config('app.key'));

        if (! hash_equals($expected, $signature)) {
            return view('payment-verify', ['valid' => false, 'error' => __('Invalid signature — this QR code may have been tampered with.')]);
        }

        if ($expires < time()) {
            return view('payment-verify', ['valid' => false, 'error' => __('This payment QR has expired. Please generate a new one.')]);
        }

        $cfg = config('cotisation');

        return view('payment-verify', [
            'valid' => true,
            'amount' => $amount,
            'communication' => $communication,
            'iban' => $cfg['iban'],
            'bic' => $cfg['bic'],
            'beneficiary' => $cfg['beneficiary'],
            'bank' => $cfg['bank'],
        ]);
    }

    /** Build a signed URL with HMAC and expiry. */
    public static function buildSignedUrl(float $amount, string $communication): string
    {
        $expires = time() + 86400 * 30; // 30 days validity
        $payload = $amount.'|'.$communication.'|'.$expires;
        $signature = hash_hmac('sha256', $payload, config('app.key'));

        return route('payment.verify', [
            'a' => $amount,
            'c' => $communication,
            'e' => $expires,
            's' => $signature,
        ]);
    }

    // ─── Legacy EPC QR (kept for backward compatibility) ───────

    public function sepaPublic(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $amount = $request->query('amount', 0);
        $communication = $request->query('communication', '');
        $iban = ThemeSetting::get('club_iban') ?: config('club.iban', '');

        if (! $iban) {
            return response('No IBAN configured', 400);
        }

        $epc = "BCD\n002\n1\nSCT\n";
        $epc .= ThemeSetting::get('club_bic')."\n";
        $epc .= ThemeSetting::get('club_full_name', 'Diving Club')."\n";
        $epc .= $iban."\n";
        $epc .= 'EUR'.number_format((float) $amount, 2, '.', '')."\n";
        $epc .= "\n";
        $epc .= $communication."\n";

        return $this->generatePng($epc, 'sepa-dues.png', false);
    }

    public function sepa(PaymentExpected $payment): \Symfony\Component\HttpFoundation\Response
    {
        $user = auth()->user();
        if ($payment->user_id !== $user->id && ! $user->can('manage payments')) {
            abort(403);
        }

        $iban = ThemeSetting::get('club_iban') ?: config('club.iban', '');
        $epc = "BCD\n002\n1\nSCT\n";
        $epc .= ThemeSetting::get('club_bic')."\n";
        $epc .= ThemeSetting::get('club_full_name', 'Diving Club')."\n";
        $epc .= $iban."\n";
        $epc .= 'EUR'.number_format($payment->amount_due, 2, '.', '')."\n";
        $epc .= "\n";
        $epc .= $payment->communication."\n";

        return $this->generatePng($epc, "sepa-{$payment->id}.png", false);
    }

    public function federation(MemberLicence $licence): \Symfony\Component\HttpFoundation\Response
    {
        $user = auth()->user();
        if ($licence->user_id !== $user->id && ! $user->isBureau() && ! $user->detail?->active_instructor) {
            abort(403);
        }

        if (! $licence->licence_number) {
            return back()->with('error', __('No licence number — licence pending.'));
        }

        // FFESSM InfoLicencié URL: requires numeric part of licence + federation key
        if ($licence->federation?->acronym === 'FFESSM' && $licence->federation_key) {
            $number = preg_replace('/^[A-Z]-\d{2}-/', '', $licence->licence_number);
            $url = "https://infolicencie.ffessm.fr/Home/InfoLicence?number={$number}&key={$licence->federation_key}";
        } else {
            // Generic fallback for other federations
            $key = hash('sha256', $licence->licence_number.config('club.id').config('club.federation_salt'));
            $url = 'https://verify.'.config('club.domain', 'example.com')."/licence/{$key}";
        }

        return $this->generatePng($url, "federation-{$licence->id}.png", false);
    }

    private function generatePng(string $data, string $filename, bool $download = true): Response
    {
        $result = Builder::create()
            ->writer(new PngWriter)
            ->data($data)
            ->encoding(new Encoding('UTF-8'))
            ->size(300)
            ->margin(10)
            ->build();

        $headers = ['Content-Type' => 'image/png'];
        if ($download) {
            $headers['Content-Disposition'] = "attachment; filename={$filename}";
        }

        return response($result->getString(), 200, $headers);
    }
}
