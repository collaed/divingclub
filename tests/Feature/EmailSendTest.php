<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class EmailSendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master']);
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
        SpatieRole::findOrCreate('member', 'web');
        $r = SpatieRole::findOrCreate('bureau_master', 'web');
        $r->givePermissionTo(Permission::findOrCreate('manage members', 'web'));
    }

    public function test_email_compose_page_loads(): void
    {
        $user = $this->createUser('bureau_master');
        $this->actingAs($user)->get('/admin/email')->assertOk();
    }

    public function test_member_cannot_access_email(): void
    {
        $user = $this->createUser('member');
        $this->actingAs($user)->get('/admin/email')->assertForbidden();
    }

    public function test_bureau_can_send_email(): void
    {
        Mail::fake();
        $user = $this->createUser('bureau_master');

        $this->actingAs($user)->post('/admin/email/send', [
            'subject' => 'Test Email',
            'body' => '<p>Hello everyone</p>',
            'target_group' => 'all_active',
        ])->assertRedirect();
    }

    public function test_group_count_returns_the_real_recipient_count_and_a_preview_list(): void
    {
        $bureau = $this->createUser('bureau_master');
        $memberA = $this->createUser('member');
        $memberB = $this->createUser('member');

        $response = $this->actingAs($bureau)->get('/admin/email/group-count?group=all');

        $response->assertOk()->assertJson(['count' => 3]); // bureau + 2 members, all email-verified
        $emails = collect($response->json('recipients'))->pluck('email');
        $this->assertTrue($emails->contains($bureau->primary_email));
        $this->assertTrue($emails->contains($memberA->primary_email));
        $this->assertTrue($emails->contains($memberB->primary_email));
    }

    public function test_email_log_excludes_entries_older_than_30_days(): void
    {
        $bureau = $this->createUser('bureau_master');
        EmailLog::create(['to_email' => 'recent@test.com', 'subject' => 'Recent', 'status' => 'sent']);
        EmailLog::create(['to_email' => 'old@test.com', 'subject' => 'Old', 'status' => 'sent'])
            ->forceFill(['created_at' => now()->subDays(45)])->save();

        $response = $this->actingAs($bureau)->get('/admin/email')->assertOk();

        $response->assertSee('Recent');
        $response->assertDontSee('Old');
    }

    public function test_group_count_rejects_an_invalid_group(): void
    {
        $bureau = $this->createUser('bureau_master');

        $this->actingAs($bureau)->get('/admin/email/group-count?group=not-a-real-group')->assertSessionHasErrors('group');
    }

    public function test_dry_run_emails_only_the_sender_and_leaves_the_group_untouched(): void
    {
        Mail::fake();
        $bureau = $this->createUser('bureau_master');
        $this->createUser('member');
        $this->createUser('member');

        $template = EmailTemplate::create([
            'name' => 'Test', 'slug' => 'test-'.uniqid(), 'subject' => 'Hi {{first_name}}', 'body' => 'Body', 'locale' => 'en',
        ]);

        $this->actingAs($bureau)->post('/admin/email/send', [
            'template_id' => $template->id,
            'group' => 'all',
            'dry_run' => '1',
        ])->assertRedirect();

        $this->assertSame(1, EmailLog::count());
        $log = EmailLog::first();
        $this->assertSame($bureau->primary_email, $log->to_email);
        $this->assertStringContainsString('[DRY RUN]', $log->subject);
        $this->assertStringContainsString('3 member', $log->body);
    }

    private function createUser(string $role = 'member'): User
    {
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        $roleId = DB::table($roleTable)->where('slug', $role)->value('id')
            ?? DB::table($roleTable)->where('name', $role)->value('id') ?? 2;

        $u = User::create([
            'username' => fake()->userName(),
            'primary_email' => fake()->unique()->safeEmail(),
            'password' => 'Password1',
            'role_id' => $roleId,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $u->assignRole($role);
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Test', 'last_name' => 'User']);

        return $u;
    }
}
