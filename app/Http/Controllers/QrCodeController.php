<?php

namespace App\Http\Controllers;

use App\Models\MemberLicence;
use App\Models\PaymentExpected;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QrCodeController extends Controller
{
    public function vcard()
    {
        $user = auth()->user();
        $d = $user->detail;

        $vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\n";
        $vcard .= "N:{$d?->last_name};{$d?->first_name}\r\n";
        $vcard .= "FN:{$user->name}\r\n";
        $vcard .= "EMAIL:{$user->primary_email}\r\n";
        if ($d?->phone_mobile) $vcard .= "TEL;TYPE=CELL:{$d->phone_mobile}\r\n";
        $vcard .= "ORG:" . \App\Models\ThemeSetting::get('club_full_name', 'Diving Club') . "\r\n";
        $vcard .= "END:VCARD\r\n";

        return $this->generatePng($vcard, "vcard-{$user->id}.png");
    }

    public function sepaPublic(Request $request)
    {
        $amount = $request->query('amount', 0);
        $communication = $request->query('communication', '');
        $iban = \App\Models\ThemeSetting::get('club_iban') ?: config('club.iban', '');

        if (!$iban) return response('No IBAN configured', 400);

        $epc = "BCD\n002\n1\nSCT\n";
        $epc .= \App\Models\ThemeSetting::get('club_bic') . "\n";
        $epc .= \App\Models\ThemeSetting::get('club_full_name', 'Diving Club') . "\n";
        $epc .= $iban . "\n";
        $epc .= "EUR" . number_format((float)$amount, 2, '.', '') . "\n";
        $epc .= "\n";
        $epc .= $communication . "\n";

        return $this->generatePng($epc, "sepa-dues.png", false);
    }

    public function sepa(PaymentExpected $payment)
    {
        $user = auth()->user();
        if ($payment->user_id !== $user->id && !$user->isBureauMaster()) abort(403);

        // SEPA EPC QR format
        $iban = \App\Models\ThemeSetting::get('club_iban') ?: config('club.iban', '');
        $epc = "BCD\n002\n1\nSCT\n";
        $epc .= \App\Models\ThemeSetting::get('club_bic') . "\n";
        $epc .= \App\Models\ThemeSetting::get('club_full_name', 'Diving Club') . "\n";
        $epc .= $iban . "\n";
        $epc .= "EUR" . number_format($payment->amount_due, 2, '.', '') . "\n";
        $epc .= "\n"; // Purpose
        $epc .= $payment->communication . "\n";

        return $this->generatePng($epc, "sepa-{$payment->id}.png", false);
    }

    public function federation(MemberLicence $licence)
    {
        $user = auth()->user();
        if ($licence->user_id !== $user->id && !$user->isBureauMaster()) abort(403);

        if (!$licence->licence_number) {
            return back()->with('error', __('No licence number — licence pending.'));
        }

        $key = hash('sha256', $licence->licence_number . config('club.id') . config('club.federation_salt'));
        $url = "https://verify." . config('club.domain', 'example.com') . "/licence/{$key}";

        return $this->generatePng($url, "federation-{$licence->id}.png");
    }

    private function generatePng(string $data, string $filename, bool $download = true): Response
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($data)
            ->encoding(new Encoding('UTF-8'))
            ->size(300)
            ->margin(10)
            ->build();

        $headers = ['Content-Type' => 'image/png'];
        if ($download) $headers['Content-Disposition'] = "attachment; filename={$filename}";

        return response($result->getString(), 200, $headers);
    }
}
