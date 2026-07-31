<?php

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class AdminMemberControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->createBureauUser();
    }

    public function test_guest_cannot_access_members(): void
    {
        $this->get(route('admin.members.index'))->assertRedirect(route('login'));
    }

    public function test_bureau_can_list_members(): void
    {
        $this->createMemberUser();

        $this->actingAs($this->admin)
            ->get(route('admin.members.index'))
            ->assertOk();
    }

    public function test_bureau_can_search_members(): void
    {
        if (config('database.default') === 'mysql') {
            $this->markTestSkipped('ILIKE not supported on MySQL — uses PostgreSQL in CI.');
        }

        $user = $this->createMemberUser();
        $user->detail->update(['first_name' => 'Alphonse', 'last_name' => 'Dupont']);

        $this->actingAs($this->admin)
            ->get(route('admin.members.index', ['search' => 'Alphonse']))
            ->assertOk();
    }

    public function test_bureau_can_view_member_profile(): void
    {
        $member = $this->createMemberUser();

        $this->actingAs($this->admin)
            ->get(route('admin.profile.show', $member))
            ->assertOk();
    }

    public function test_bureau_can_impersonate_member(): void
    {
        $member = $this->createMemberUser();

        $this->actingAs($this->admin)
            ->post(route('admin.impersonate', $member))
            ->assertRedirect();

        $this->assertEquals($member->id, auth()->id());
    }

    public function test_bureau_can_send_password_reset(): void
    {
        $member = $this->createMemberUser();

        $this->actingAs($this->admin)
            ->post(route('admin.send-reset', $member))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

}
