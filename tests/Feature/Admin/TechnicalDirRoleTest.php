<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class TechnicalDirRoleTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Role::findOrCreate('technical_dir', 'web');
    }

    private function technicalDir(): User
    {
        $u = User::create([
            'username' => 'td'.uniqid(),
            'primary_email' => 'td'.uniqid().'@test.com',
            'password' => 'Password1',
            'role_id' => 2,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $u->assignRole('technical_dir');

        return $u;
    }

    public function test_technical_dir_can_reach_its_delegated_areas(): void
    {
        $td = $this->technicalDir();

        $this->actingAs($td)->get(route('admin.federations.index'))->assertOk();
        $this->actingAs($td)->get(route('admin.equipment.index'))->assertOk();
    }

    public function test_technical_dir_cannot_reach_other_admin_pages(): void
    {
        $td = $this->technicalDir();

        $this->actingAs($td)->get(route('admin.members.index'))->assertForbidden();
        $this->actingAs($td)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($td)->get(route('admin.analytics.index'))->assertForbidden();
    }

    public function test_technical_dir_nav_shows_delegated_links_only(): void
    {
        $html = $this->actingAs($this->technicalDir())->get(route('admin.federations.index'))->getContent();

        $this->assertStringContainsString(route('admin.federations.index'), $html);
        $this->assertStringContainsString(route('admin.equipment.index'), $html);
        $this->assertStringNotContainsString(route('admin.members.index'), $html);
    }

    public function test_plain_member_is_denied_the_federations_area(): void
    {
        $member = User::create([
            'username' => 'm'.uniqid(), 'primary_email' => 'm'.uniqid().'@test.com',
            'password' => 'Password1', 'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now(),
        ]);
        $member->assignRole('member');

        $this->actingAs($member)->get(route('admin.federations.index'))->assertForbidden();
    }

    public function test_bureau_master_still_reaches_the_federations_area(): void
    {
        $this->actingAs($this->createBureauUser())
            ->get(route('admin.federations.index'))
            ->assertOk();
    }
}
