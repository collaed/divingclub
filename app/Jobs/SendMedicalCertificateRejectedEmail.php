<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendMedicalCertificateRejectedEmail implements ShouldQueue
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

        try {
            $html = view('emails.medical-certificate-rejected', ['document' => $document])->render();

            Mail::html($html, fn ($m) => $m->to($email)->subject(__('Your medical certificate needs a new upload')));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
