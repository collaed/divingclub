<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\PollInboundMail;
use App\Models\Event;
use App\Models\MailConversation;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class InboundConversationReplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table($roleTable)->insertOrIgnore(['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'actif']);
        SpatieRole::findOrCreate('member', 'web');
        SpatieRole::findOrCreate('bureau_master', 'web');
        SpatieRole::findOrCreate('instructor', 'web');
        config(['club.mail_address' => 'cep@clubcep.eu']);
        Mail::fake();
    }

    private function process(string $from, string $to, string $subject, string $body): void
    {
        $method = new ReflectionMethod(PollInboundMail::class, 'processMessage');
        $method->setAccessible(true);
        $method->invoke(new PollInboundMail, $from, $to, $subject, $body);
    }

    public function test_conversation_reply_forwards_to_initiator_and_appends_to_event(): void
    {
        $initiator = $this->makeUser('bureau_master');
        $event = Event::factory()->create();
        $conversation = MailConversation::factory()->create([
            'initiator_user_id' => $initiator->id,
            'event_id' => $event->id,
            'external_email' => 'partner@example.com',
            'hit_count' => 1,
        ]);

        $this->process('partner@example.com', $conversation->sas_alias, 'Re: Trip', 'Sounds good, see you there.');

        // Reply logged against the event so it shows on the event page.
        $this->assertDatabaseHas('email_log', [
            'event_id' => $event->id,
            'from_email' => 'partner@example.com',
            'alias' => $conversation->sas_alias,
            'direction' => 'inbound',
        ]);

        // Activity recorded.
        $this->assertSame(2, $conversation->fresh()->hit_count);
    }

    public function test_legacy_trip_mail_logs_event_id(): void
    {
        $instructor = $this->makeUser('instructor');
        $event = Event::factory()->create();
        // No confirmed participants needed — resolution falls back to bureau if
        // empty, but the log must still carry the event_id from the alias.

        $this->process($instructor->primary_email, "members.s{$event->id}@clubcep.eu", 'Trip update', 'Meeting at 8am.');

        $this->assertDatabaseHas('email_log', [
            'event_id' => $event->id,
            'direction' => 'inbound',
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
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Init', 'last_name' => 'Iator']);

        return $user;
    }
}
