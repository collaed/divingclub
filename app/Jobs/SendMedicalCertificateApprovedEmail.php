<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendMedicalCertificateApprovedEmail implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $documentId) {}

    public function handle(): void
    {
        $document = Document::with('user')->find($this->documentId);
        $email = $document?->user?->primary_email;
        if (! $document || ! $email) {
            return;
        }

        // "FFESSM: 31/12/2026 | FLASSA: 30/06/2026" -> one line per federation.
        $perFederation = $document->compliance_notes
            ? array_map('trim', explode('|', $document->compliance_notes))
            : [];

        try {
            $html = view('emails.medical-certificate-approved', [
                'document' => $document,
                'perFederation' => $perFederation,
            ])->render();

            Mail::html($html, fn ($m) => $m->to($email)->subject(__('Your medical certificate has been validated')));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
