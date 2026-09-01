<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAlias;
use App\Models\MailConversation;
use App\Models\MemberDetail;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConversationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'actif']);
        config(['club.mail_address' => 'cep@clubcep.eu']);
    }

    public function test_start_mints_conversation_and_alias(): void
    {
        $initiator = $this->makeUser();

        $conversation = ConversationService::start($initiator, 'External Party@Example.com ', 'Hello there', null, 'Jane Doe');

        $this->assertSame($initiator->id, $conversation->initiator_user_id);
        $this->assertSame('external party@example.com', $conversation->external_email);
        $this->assertSame('Jane Doe', $conversation->external_name);
        $this->assertSame("cep+conv.{$conversation->token}@clubcep.eu", $conversation->sas_alias);
        $this->assertNotNull($conversation->last_activity_at);

        $this->assertDatabaseHas('mail_aliases', [
            'user_id' => $initiator->id,
            'alias' => $conversation->sas_alias,
            'type' => 'sas_conv',
        ]);
    }

    public function test_match_token_round_trips_from_full_address(): void
    {
        $conversation = MailConversation::factory()->create([
            'initiator_user_id' => $this->makeUser()->id,
        ]);

        $matched = ConversationService::matchToken($conversation->sas_alias);
        $this->assertNotNull($matched);
        $this->assertSame($conversation->id, $matched->id);

        // Also matches the bare "conv.{token}" tag and the bare token.
        $this->assertSame($conversation->id, ConversationService::matchToken("conv.{$conversation->token}")?->id);
        $this->assertSame($conversation->id, ConversationService::matchToken($conversation->token)?->id);
    }

    public function test_match_token_returns_null_for_non_conversation_alias(): void
    {
        $this->assertNull(ConversationService::matchToken('bureau@clubcep.eu'));
        $this->assertNull(ConversationService::matchToken('members.s42@clubcep.eu'));
    }

    public function test_record_activity_increments_hit_count(): void
    {
        $conversation = MailConversation::factory()->create([
            'initiator_user_id' => $this->makeUser()->id,
            'hit_count' => 1,
        ]);

        ConversationService::recordActivity($conversation);

        $this->assertSame(2, $conversation->fresh()->hit_count);
        $this->assertNotNull($conversation->fresh()->last_activity_at);
    }

    public function test_tokens_are_unique_across_starts(): void
    {
        $initiator = $this->makeUser();

        $a = ConversationService::start($initiator, 'a@example.com', 'A');
        $b = ConversationService::start($initiator, 'b@example.com', 'B');

        $this->assertNotSame($a->token, $b->token);
        $this->assertSame(2, MailAlias::where('type', 'sas_conv')->count());
    }

    private function makeUser(): User
    {
        $user = User::create([
            'username' => fake()->unique()->userName(),
            'primary_email' => fake()->unique()->safeEmail(),
            'password' => 'Password1',
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'User']);

        return $user;
    }
}
