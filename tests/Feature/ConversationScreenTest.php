<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MailConversation;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class ConversationScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master']);
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'actif']);
        SpatieRole::findOrCreate('member', 'web');
        $r = SpatieRole::findOrCreate('bureau_master', 'web');
        $r->givePermissionTo(Permission::findOrCreate('send email', 'web'));
        config([
            'club.mail_address' => 'cep@clubcep.eu',
            'club.noreply_address' => 'no-reply@clubcep.eu',
            'club.log_mailbox' => 'mail-log@clubcep.eu',
        ]);
    }

    public function test_screen_loads_for_bureau(): void
    {
        $bureau = $this->makeUser('bureau_master');

        $this->actingAs($bureau)->get(route('admin.conversations.index'))->assertOk();
    }

    public function test_non_bureau_cannot_access(): void
    {
        $member = $this->makeUser('member');

        $this->actingAs($member)->get(route('admin.conversations.index'))->assertForbidden();
        $this->actingAs($member)->post(route('admin.conversations.store'), [
            'external_email' => 'x@example.com', 'subject' => 'Hi', 'message' => 'Body',
        ])->assertForbidden();
    }

    public function test_sending_creates_conversation_with_dual_reply_to(): void
    {
        Mail::fake();
        $bureau = $this->makeUser('bureau_master');

        $this->actingAs($bureau)->post(route('admin.conversations.store'), [
            'external_email' => 'partner@example.com',
            'external_name' => 'Partner Co',
            'subject' => 'Collaboration',
            'message' => 'Hello, we would like to discuss.',
        ])->assertRedirect(route('admin.conversations.index'));

        $conversation = MailConversation::first();
        $this->assertNotNull($conversation);
        $this->assertSame('partner@example.com', $conversation->external_email);
        $this->assertStringStartsWith('cep+conv.', $conversation->sas_alias);

        // Alias recorded and log written with the conversation alias.
        $this->assertDatabaseHas('email_log', [
            'to_email' => 'partner@example.com',
            'from_email' => 'no-reply@clubcep.eu',
            'alias' => $conversation->sas_alias,
            'direction' => 'contact',
        ]);
        $this->assertDatabaseHas('mail_aliases', [
            'alias' => $conversation->sas_alias,
            'type' => 'sas_conv',
        ]);
    }

    public function test_previously_used_addresses_ranked_by_hit_count(): void
    {
        $bureau = $this->makeUser('bureau_master');
        MailConversation::factory()->create(['external_email' => 'low@example.com', 'external_name' => 'Low', 'hit_count' => 1, 'initiator_user_id' => $bureau->id]);
        MailConversation::factory()->create(['external_email' => 'high@example.com', 'external_name' => 'High', 'hit_count' => 9, 'initiator_user_id' => $bureau->id]);

        $response = $this->actingAs($bureau)->get(route('admin.conversations.index'))->assertOk();

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'low@example.com'),
            strpos($content, 'high@example.com'),
            'Higher hit_count address should appear before the lower one.'
        );
    }

    public function test_event_link_is_persisted_on_conversation_and_log(): void
    {
        Mail::fake();
        $bureau = $this->makeUser('bureau_master');
        $event = Event::factory()->create();

        $this->actingAs($bureau)->post(route('admin.conversations.store'), [
            'external_email' => 'guide@example.com',
            'subject' => 'Trip logistics',
            'message' => 'Details about the trip.',
            'event_id' => $event->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('mail_conversations', [
            'external_email' => 'guide@example.com', 'event_id' => $event->id,
        ]);
        $this->assertDatabaseHas('email_log', [
            'to_email' => 'guide@example.com', 'event_id' => $event->id, 'direction' => 'contact',
        ]);
    }

    private function makeUser(string $role): User
    {
        $roleTable = Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        $roleId = DB::table($roleTable)->where('slug', $role)->value('id') ?? 2;

        $user = User::create([
            'username' => fake()->unique()->userName(),
            'primary_email' => fake()->unique()->safeEmail(),
            'password' => 'Password1',
            'role_id' => $roleId,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Bur', 'last_name' => 'Eau']);

        return $user;
    }
}
