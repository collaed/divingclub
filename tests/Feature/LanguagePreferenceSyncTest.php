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
class LanguagePreferenceSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SpatieRole::findOrCreate('member', 'web');
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
    }

    private function member(): User
    {
        $u = User::create([
            'primary_email' => 'm'.uniqid().'@example.com', 'password' => 'Password1!',
            'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now(), 'preferred_locale' => 'en',
        ]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'M', 'last_name' => 'E', 'preferred_language' => 'en']);

        return $u;
    }

    public function test_updating_the_language_tab_syncs_users_preferred_locale(): void
    {
        $u = $this->member();

        $this->actingAs($u)->post('/profile/language', ['preferred_language' => 'fr'])
            ->assertSessionHasNoErrors();

        $this->assertSame('fr', $u->detail->fresh()->preferred_language);
        $this->assertSame('fr', $u->fresh()->preferred_locale);
    }

    public function test_backfill_migration_heals_existing_drift(): void
    {
        $u = $this->member();
        // Simulate the historic bug: detail says fr, user column still en.
        $u->detail->update(['preferred_language' => 'de']);
        DB::table('users')->where('id', $u->id)->update(['preferred_locale' => 'en']);

        (require database_path('migrations/2026_09_07_223000_sync_preferred_locale_from_member_details.php'))->up();

        $this->assertSame('de', $u->fresh()->preferred_locale);
    }
}
