<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailListContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailListUnsubscribeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_token_unsubscribes_the_contact(): void
    {
        $contact = MailListContact::create(['email' => 'jane@example.com']);

        $this->get(route('mail-list.unsubscribe', $contact->unsubscribe_token))->assertOk();

        $this->assertFalse($contact->fresh()->subscribed);
    }

    public function test_an_invalid_token_404s(): void
    {
        $this->get(route('mail-list.unsubscribe', 'not-a-real-token'))->assertNotFound();
    }

    public function test_visiting_an_already_unsubscribed_link_again_does_not_error(): void
    {
        $contact = MailListContact::create(['email' => 'jane@example.com', 'subscribed' => false]);

        $this->get(route('mail-list.unsubscribe', $contact->unsubscribe_token))->assertOk();

        $this->assertFalse($contact->fresh()->subscribed);
    }
}
