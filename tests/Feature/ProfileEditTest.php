<?php

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class ProfileEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
        SpatieRole::findOrCreate('member', 'web');
        SpatieRole::findOrCreate('bureau_master', 'web');
    }

    public function test_profile_page_loads(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_member_can_update_info(): void
    {
        $user = $this->createUser();
        $response = $this->actingAs($user)->post('/profile/info', [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'phone_mobile' => '+352621123456',
            'sex' => 'M',
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_member_can_update_language(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->post('/profile/language', [
            'locale' => 'fr',
        ])->assertRedirect();
    }

    public function test_guest_cannot_access_profile(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    private function createUser(): User
    {
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        $roleId = DB::table($roleTable)->where('slug', 'member')->value('id')
            ?? DB::table($roleTable)->where('name', 'member')->value('id') ?? 2;

        $u = User::create([
            'username' => fake()->userName(),
            'primary_email' => fake()->unique()->safeEmail(),
            'password' => 'Password1',
            'role_id' => $roleId,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Test', 'last_name' => 'User']);

        return $u;
    }
}
