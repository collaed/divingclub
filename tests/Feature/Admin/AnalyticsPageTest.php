<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p2')]
class AnalyticsPageTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Permission::findOrCreate('view analytics', 'web');
    }

    public function test_bureau_master_can_open_analytics(): void
    {
        config(['services.umami.share_url' => 'https://analytics.example/share/abc/cep']);

        $this->actingAs($this->createBureauUser())
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('analytics.example/share/abc/cep', false);
    }

    public function test_shows_setup_hint_when_share_url_missing(): void
    {
        config(['services.umami.share_url' => null]);

        $this->actingAs($this->createBureauUser())
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('not configured yet');
    }

    public function test_member_without_the_permission_is_denied(): void
    {
        $member = User::create([
            'username' => 'm'.uniqid(), 'primary_email' => 'm'.uniqid().'@t.com',
            'password' => 'Password1', 'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now(),
        ]);
        $member->assignRole('member');

        $this->actingAs($member)->get(route('admin.analytics.index'))->assertForbidden();
    }
}
