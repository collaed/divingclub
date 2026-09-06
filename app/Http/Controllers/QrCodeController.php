<?php

declare(strict_types=1);

/**
 * QR code generation: vCard contact card and federation licence verification.
 *
 * Payment/SEPA/EPC QR codes were retired: the EPC standard is deprecated and
 * Wero is becoming a closed standard. Dues payments now rely on the printed
 * IBAN + structured communication on the /dues page.
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Controllers;

use App\Models\MemberLicence;
use App\Models\ThemeSetting;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
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
