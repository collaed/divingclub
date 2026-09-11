<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

#[Group('p1')]
class BrevoTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.brevo.key' => 'test-brevo-key']);
    }

    public function test_sends_via_the_brevo_api_with_the_expected_payload(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'abc123'], 201)]);

        Mail::mailer('brevo')->html('<p>Hello there</p>', function ($m) {
            $m->to('member@example.com', 'A Member')
                ->from('club@clubcep.eu', 'Club CEP')
                ->subject('Test subject');
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'test-brevo-key')
                && $request['subject'] === 'Test subject'
                && $request['sender']['email'] === 'club@clubcep.eu'
                && $request['to'][0]['email'] === 'member@example.com'
                && str_contains($request['htmlContent'], 'Hello there');
        });
    }

    public function test_throws_when_brevo_rejects_the_send(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'invalid key'], 401)]);

        $this->expectException(TransportException::class);

        Mail::mailer('brevo')->html('<p>x</p>', function ($m) {
            $m->to('member@example.com')->from('club@clubcep.eu')->subject('x');
        });
    }
}
