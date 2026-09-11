<?php

namespace Tests\Feature;

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

    public function test_bureau_can_create_a_member_manually(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.members.store'), [
                'first_name' => 'Jean',
                'last_name' => 'Dupont',
                'email' => 'jean.dupont@example.com',
            ])
            ->assertRedirect();

        $user = User::where('primary_email', 'jean.dupont@example.com')->firstOrFail();
        $this->assertSame('Jean', $user->detail->first_name);
        $this->assertSame('Dupont', $user->detail->last_name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('member'));
        $this->assertTrue($user->emails()->where('email', 'jean.dupont@example.com')->where('is_primary', true)->exists());
    }

    public function test_creating_a_member_requires_a_unique_email(): void
    {
        $existing = $this->createMemberUser();

        $this->actingAs($this->admin)
            ->post(route('admin.members.store'), [
                'first_name' => 'Jean',
                'last_name' => 'Dupont',
                'email' => $existing->primary_email,
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_a_regular_member_cannot_create_a_member(): void
    {
        $this->actingAs($this->createMemberUser())
            ->post(route('admin.members.store'), [
                'first_name' => 'Jean',
                'last_name' => 'Dupont',
                'email' => 'jean.dupont@example.com',
            ])
            ->assertForbidden();
    }
}
