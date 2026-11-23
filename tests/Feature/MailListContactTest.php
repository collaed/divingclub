<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailListContact;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailListContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unsubscribe_token_is_generated_automatically_on_create(): void
    {
        $contact = MailListContact::create(['email' => 'jane@example.com']);

        $this->assertNotEmpty($contact->unsubscribe_token);
    }

    public function test_duplicate_emails_are_rejected(): void
    {
        MailListContact::create(['email' => 'jane@example.com']);

        $this->expectException(QueryException::class);
        MailListContact::create(['email' => 'jane@example.com']);
    }
}
