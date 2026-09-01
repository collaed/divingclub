<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAlias;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class MailAliasManagementTest extends TestCase
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
        $r->givePermissionTo(Permission::findOrCreate('manage members', 'web'));
    }

    public function test_bureau_can_save_suggested_alias(): void
    {
        $bureau = $this->makeUser('bureau_master', 'Boss', 'Man');
        $target = $this->makeUser('member', 'Jean', 'Dupont');

        $this->actingAs($bureau)
            ->post(route('admin.members.mail-alias.store', $target), ['alias' => 'jean'])
            ->assertRedirect();

        $this->assertDatabaseHas('mail_aliases', [
            'user_id' => $target->id, 'alias' => 'jean', 'type' => 'member',
        ]);
    }

    public function test_suggest_endpoint_returns_unique_suggestion(): void
    {
        $bureau = $this->makeUser('bureau_master', 'Boss', 'Man');
        MailAlias::factory()->create(['alias' => 'jean']);
        $target = $this->makeUser('member', 'Jean', 'Dupont');

        $this->actingAs($bureau)
            ->getJson(route('admin.members.mail-alias.suggest', $target))
            ->assertOk()
            ->assertJson(['suggestion' => 'jeand']);
    }

    public function test_override_to_taken_alias_fails_validation(): void
    {
        $bureau = $this->makeUser('bureau_master', 'Boss', 'Man');
        $other = $this->makeUser('member', 'Other', 'Person');
        MailAlias::factory()->create(['alias' => 'taken', 'user_id' => $other->id, 'type' => 'member']);
        $target = $this->makeUser('member', 'Jean', 'Dupont');

        $this->actingAs($bureau)
            ->post(route('admin.members.mail-alias.store', $target), ['alias' => 'taken'])
            ->assertSessionHasErrors('alias');

        $this->assertDatabaseMissing('mail_aliases', [
            'user_id' => $target->id, 'alias' => 'taken',
        ]);
    }

    public function test_updating_own_alias_keeps_uniqueness(): void
    {
        $bureau = $this->makeUser('bureau_master', 'Boss', 'Man');
        $target = $this->makeUser('member', 'Jean', 'Dupont');
        MailAlias::factory()->create(['alias' => 'jean', 'user_id' => $target->id, 'type' => 'member']);

        // Re-saving the same alias for the same member must succeed (ignore self).
        $this->actingAs($bureau)
            ->post(route('admin.members.mail-alias.store', $target), ['alias' => 'jean'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, MailAlias::where('user_id', $target->id)->where('type', 'member')->count());
    }

    public function test_non_bureau_cannot_manage_alias(): void
    {
        $member = $this->makeUser('member', 'Reg', 'Ular');
        $target = $this->makeUser('member', 'Jean', 'Dupont');

        $this->actingAs($member)
            ->post(route('admin.members.mail-alias.store', $target), ['alias' => 'jean'])
            ->assertForbidden();
    }

    public function test_invalid_alias_characters_rejected(): void
    {
        $bureau = $this->makeUser('bureau_master', 'Boss', 'Man');
        $target = $this->makeUser('member', 'Jean', 'Dupont');

        $this->actingAs($bureau)
            ->post(route('admin.members.mail-alias.store', $target), ['alias' => 'Jean Dupont!'])
            ->assertSessionHasErrors('alias');
    }

    private function makeUser(string $role, string $first, string $last): User
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
        MemberDetail::create(['user_id' => $user->id, 'first_name' => $first, 'last_name' => $last]);

        return $user;
    }
}
