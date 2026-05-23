<?php

namespace Tests\Feature;

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
