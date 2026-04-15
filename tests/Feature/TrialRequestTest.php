<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @group p1
 */
class TrialRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_page_loads(): void
    {
        $this->get('/trial')->assertOk();
    }

    public function test_submit_trial_request(): void
    {
        $this->post('/trial', [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@example.com',
            'phone' => '+352 621 123 456',
            'message' => 'I want to try diving!',
            'website' => '', // honeypot
            '_ts' => time() - 5,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('trial_requests', ['email' => 'jean@example.com', 'first_name' => 'Jean']);
    }

    public function test_honeypot_rejects_bots(): void
    {
        $this->post('/trial', [
            'first_name' => 'Bot',
            'last_name' => 'Spam',
            'email' => 'bot@spam.com',
            'website' => 'http://spam.com', // honeypot filled = bot
            '_ts' => time() - 5,
        ]);

        $this->assertDatabaseMissing('trial_requests', ['email' => 'bot@spam.com']);
    }

    public function test_timestamp_rejects_fast_submit(): void
    {
        $this->post('/trial', [
            'first_name' => 'Fast',
            'last_name' => 'Bot',
            'email' => 'fast@bot.com',
            'website' => '',
            '_ts' => time(), // submitted instantly = bot
        ])->assertSessionHas('error');

        $this->assertDatabaseMissing('trial_requests', ['email' => 'fast@bot.com']);
    }

    public function test_validates_required_fields(): void
    {
        $this->post('/trial', ['website' => '', '_ts' => time() - 5])
            ->assertSessionHasErrors(['first_name', 'last_name', 'email']);
    }
}
