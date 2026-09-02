<?php

namespace Tests\Feature;

use App\Models\Federation;
use App\Models\MemberDetail;
use App\Models\MemberLicence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class AddLicenceTest extends TestCase
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

    public function test_bureau_can_add_flassa_licence_for_member(): void
    {
        $admin = $this->createUser('bureau_master');
        $member = $this->createUser('member');
        $flassa = Federation::create(['acronym' => 'FLASSA', 'full_name' => 'Fédération Luxembourgeoise', 'visibility' => 'active']);

        $response = $this->actingAs($admin)->post("/profile/{$member->id}/licence", [
            'federation_id' => $flassa->id,
            'licence_number' => 'LUX-2026-0042',
            'season' => '2026',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('member_licences', [
            'user_id' => $member->id,
            'federation_id' => $flassa->id,
            'licence_number' => 'LUX-2026-0042',
            'season' => '2026',
        ]);
    }

    public function test_cannot_add_duplicate_federation_licence(): void
    {
        $admin = $this->createUser('bureau_master');
        $member = $this->createUser('member');
        $flassa = Federation::create(['acronym' => 'FLASSA', 'full_name' => 'Fédération Luxembourgeoise', 'visibility' => 'active']);
        MemberLicence::create(['user_id' => $member->id, 'federation_id' => $flassa->id, 'licence_number' => 'EXISTING']);

        $response = $this->actingAs($admin)->post("/profile/{$member->id}/licence", [
            'federation_id' => $flassa->id,
            'licence_number' => 'DUPLICATE',
        ]);

        $response->assertSessionHasErrors('federation_id');
        $this->assertDatabaseMissing('member_licences', [
            'user_id' => $member->id,
            'licence_number' => 'DUPLICATE',
        ]);
    }

    public function test_regular_member_cannot_add_licence(): void
    {
        $member = $this->createUser('member');
        $other = $this->createUser('member');
        $flassa = Federation::create(['acronym' => 'FLASSA', 'full_name' => 'Fédération Luxembourgeoise', 'visibility' => 'active']);

        $this->actingAs($member)->post("/profile/{$other->id}/licence", [
            'federation_id' => $flassa->id,
            'licence_number' => 'HACK',
        ])->assertForbidden();

        $this->assertDatabaseMissing('member_licences', ['licence_number' => 'HACK']);
    }

    public function test_federation_is_required(): void
    {
        $admin = $this->createUser('bureau_master');
        $member = $this->createUser('member');

        $this->actingAs($admin)->post("/profile/{$member->id}/licence", [
            'licence_number' => 'NO-FED',
        ])->assertSessionHasErrors('federation_id');
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
