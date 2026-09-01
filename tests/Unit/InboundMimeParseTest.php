<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\PollInboundMail;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Verifies the zbateson-backed MIME parsing in PollInboundMail::parseRaw()
 * handles multipart, base64, quoted-printable and encoded headers — the cases
 * that plagued the legacy hand-rolled parser.
 */
class InboundMimeParseTest extends TestCase
{
    private function parse(string $raw): array
    {
        $method = new ReflectionMethod(PollInboundMail::class, 'parseRaw');
        $method->setAccessible(true);

        return $method->invoke(new PollInboundMail, $raw);
    }

    public function test_parses_simple_plain_text_message(): void
    {
        $raw = "From: Alice <alice@example.com>\r\n".
            "To: bureau@clubcep.eu\r\n".
            "Subject: Hello\r\n".
            "Message-ID: <plain-1@example.com>\r\n".
            "Content-Type: text/plain; charset=utf-8\r\n\r\n".
            "This is the body.\r\n";

        $parsed = $this->parse($raw);

        $this->assertSame('alice@example.com', $parsed['from']);
        $this->assertSame('bureau@clubcep.eu', $parsed['to']);
        $this->assertSame('Hello', $parsed['subject']);
        // zbateson returns the Message-ID without surrounding angle brackets.
        $this->assertSame('plain-1@example.com', $parsed['message_id']);
        $this->assertStringContainsString('This is the body.', $parsed['body']);
    }

    public function test_prefers_html_part_in_multipart_alternative(): void
    {
        $boundary = 'BOUND123';
        $raw = "From: Bob <bob@example.com>\r\n".
            "To: members.s42@clubcep.eu\r\n".
            "Subject: Trip\r\n".
            "MIME-Version: 1.0\r\n".
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n".
            "--{$boundary}\r\n".
            "Content-Type: text/plain; charset=utf-8\r\n\r\n".
            "Plain version\r\n".
            "--{$boundary}\r\n".
            "Content-Type: text/html; charset=utf-8\r\n\r\n".
            "<p>HTML version</p>\r\n".
            "--{$boundary}--\r\n";

        $parsed = $this->parse($raw);

        $this->assertStringContainsString('HTML version', $parsed['body']);
        $this->assertSame('bob@example.com', $parsed['from']);
    }

    public function test_decodes_base64_body(): void
    {
        $encoded = base64_encode('Décodé en base64');
        $raw = "From: c@example.com\r\n".
            "To: bureau@clubcep.eu\r\n".
            "Subject: B64\r\n".
            "Content-Type: text/plain; charset=utf-8\r\n".
            "Content-Transfer-Encoding: base64\r\n\r\n".
            $encoded."\r\n";

        $parsed = $this->parse($raw);

        $this->assertStringContainsString('Décodé en base64', $parsed['body']);
    }

    public function test_decodes_quoted_printable_body(): void
    {
        $raw = "From: d@example.com\r\n".
            "To: bureau@clubcep.eu\r\n".
            "Subject: QP\r\n".
            "Content-Type: text/plain; charset=utf-8\r\n".
            "Content-Transfer-Encoding: quoted-printable\r\n\r\n".
            "Caf=C3=A9 au lait\r\n";

        $parsed = $this->parse($raw);

        $this->assertStringContainsString('Café au lait', $parsed['body']);
    }

    public function test_decodes_encoded_subject_header(): void
    {
        $raw = "From: e@example.com\r\n".
            "To: bureau@clubcep.eu\r\n".
            "Subject: =?UTF-8?Q?R=C3=A9union?=\r\n".
            "Content-Type: text/plain\r\n\r\n".
            "body\r\n";

        $parsed = $this->parse($raw);

        $this->assertSame('Réunion', $parsed['subject']);
    }
}
