<?php

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class NewsletterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table($roleTable)->insertOrIgnore(['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
        SpatieRole::findOrCreate('member', 'web');
        $r = SpatieRole::findOrCreate('bureau_master', 'web');
        $r->givePermissionTo(Permission::findOrCreate('manage members', 'web'));
    }

    public function test_newsletter_index_loads_for_bureau(): void
    {
        $user = $this->createUser('bureau_master');
        $this->actingAs($user)->get('/admin/newsletters')->assertOk();
    }

    public function test_member_cannot_access_newsletters(): void
    {
        $user = $this->createUser('member');
        $this->actingAs($user)->get('/admin/newsletters')->assertForbidden();
    }

    public function test_bureau_can_access_create_page(): void
    {
        $user = $this->createUser('bureau_master');
        $this->actingAs($user)->get('/admin/newsletters/create')->assertOk();
    }

    public function test_newsletter_requires_approval_before_send(): void
    {
        $user = $this->createUser('bureau_master');
        $newsletter = Newsletter::create([
            'title' => 'Test', 'month' => '2026-05', 'status' => 'draft',
            'created_by' => $user->id, 'slots' => json_encode([]),
        ]);

        $this->actingAs($user)->post("/admin/newsletters/{$newsletter->id}/send")
            ->assertStatus(403);
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
