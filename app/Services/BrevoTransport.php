<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends mail through Brevo's transactional email API — POST /v3/smtp/email
 * (the "smtp" in the path is just Brevo's own naming; this is a plain HTTP
 * call with an api-key header, not literal SMTP). Registered as the 'brevo'
 * mailer via Mail::extend() in AppServiceProvider, alongside Resend/Mailjet
 * in MailBalancer's rotation.
 */
class BrevoTransport extends AbstractTransport
{
    public function __construct(private readonly string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();
        if (! $email instanceof Email) {
            throw new TransportException('Brevo transport only supports Email messages.');
        }

        $from = $email->getFrom()[0] ?? null;
        if (! $from) {
            throw new TransportException('Brevo send requires a From address.');
        }

        $payload = [
            'sender' => array_filter(['email' => $from->getAddress(), 'name' => $from->getName() ?: null]),
            'to' => array_map(fn (Address $a): array => array_filter(['email' => $a->getAddress(), 'name' => $a->getName() ?: null]), $email->getTo()),
            'subject' => $email->getSubject() ?? '',
            'htmlContent' => $email->getHtmlBody() ?: nl2br(e((string) $email->getTextBody())),
        ];

        if ($cc = $email->getCc()) {
            $payload['cc'] = array_map(fn (Address $a): array => array_filter(['email' => $a->getAddress(), 'name' => $a->getName() ?: null]), $cc);
        }
        if ($replyTo = $email->getReplyTo()) {
            $payload['replyTo'] = array_filter(['email' => $replyTo[0]->getAddress(), 'name' => $replyTo[0]->getName() ?: null]);
        }

        $response = Http::withHeaders(['api-key' => $this->apiKey, 'Content-Type' => 'application/json'])
            ->post('https://api.brevo.com/v3/smtp/email', $payload);

        if (! $response->successful()) {
            throw new TransportException('Brevo send failed ('.$response->status().'): '.$response->body());
        }
    }

    public function __toString(): string
    {
        return 'brevo+api://default';
    }
}
