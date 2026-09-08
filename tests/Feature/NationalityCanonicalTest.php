<?php

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

#[Group('p1')]
class NationalityCanonicalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['member'] as $r) {
            SpatieRole::findOrCreate($r, 'web');
        }
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
    }

    /** @return array<string, mixed> */
    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean'.uniqid().'@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'website' => '',
            'date_of_birth' => '1990-05-15',
            'sex' => 'M',
            'phone_mobile' => '+352691000000',
            '_ts' => time() - 5,
        ], $overrides);
    }

    public function test_register_accepts_a_canonical_nationality(): void
    {
        $this->post('/register', $this->registerPayload(['nationality' => 'Germany']))
            ->assertRedirect();
        $this->assertDatabaseHas('member_details', ['nationality' => 'Germany']);
    }

    public function test_register_accepts_no_nationality(): void
    {
        $this->post('/register', $this->registerPayload())->assertRedirect();
    }

    public function test_register_rejects_a_non_canonical_nationality(): void
    {
        $this->post('/register', $this->registerPayload(['nationality' => 'USA']))
            ->assertSessionHasErrors('nationality');
        $this->assertDatabaseMissing('member_details', ['nationality' => 'USA']);
    }

    private function member(?string $nationality = null): User
    {
        $u = User::create([
            'primary_email' => 'm'.uniqid().'@example.com',
            'password' => 'Password1!',
            'role_id' => 2,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'M', 'last_name' => 'E', 'nationality' => $nationality, 'sex' => 'M']);

        return $u;
    }

    public function test_profile_update_rejects_a_new_non_canonical_nationality(): void
    {
        $u = $this->member('France');

        $this->actingAs($u)->from('/profile')->post('/profile/info', [
            'first_name' => 'M', 'last_name' => 'E', 'sex' => 'M',
            'nationality' => 'Deutschland',
        ])->assertSessionHasErrors('nationality');

        $this->assertSame('France', $u->detail->fresh()->nationality);
    }

    public function test_profile_update_tolerates_the_members_existing_legacy_value(): void
    {
        $u = $this->member('Czech Republic'); // legacy, not in config('countries.all')

        $this->actingAs($u)->post('/profile/info', [
            'first_name' => 'Marie', 'last_name' => 'E', 'sex' => 'M',
            'nationality' => 'Czech Republic',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Marie', $u->detail->fresh()->first_name);
    }

    public function test_profile_update_lets_the_member_switch_to_a_canonical_value(): void
    {
        $u = $this->member('Czech Republic');

        $this->actingAs($u)->post('/profile/info', [
            'first_name' => 'M', 'last_name' => 'E', 'sex' => 'M',
            'nationality' => 'Czechia',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Czechia', $u->detail->fresh()->nationality);
    }
}
