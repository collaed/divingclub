<?php

namespace Tests\Feature;

use App\Models\MemberStatus;
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
        MemberStatus::firstOrCreate(['slug' => 'actif'], ['name' => 'Actif']);

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
        // Not left null (which would misread as "pending approval") — see
        // DashboardController::new_members_unconfirmed.
        $this->assertNotNull($user->status_id);
        $this->assertSame('actif', $user->status?->slug);
    }

    public function test_active_only_filter_uses_isactive_not_the_literal_actif_status(): void
    {
        $paidUp = $this->createMemberUser();
        $paidUp->detail->update(['cotisation_years' => [(string) now()->year]]);

        $unpaid = $this->createMemberUser();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.members.index', ['active_only' => '1']))
            ->assertOk();

        $this->assertTrue($response->viewData('members')->contains('id', $paidUp->id));
        $this->assertFalse($response->viewData('members')->contains('id', $unpaid->id));
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

    public function test_erased_members_are_hidden_from_the_normal_list_but_shown_in_the_erased_view(): void
    {
        $visible = $this->createMemberUser();
        $erased = $this->erasedMemberUser();

        $normal = $this->actingAs($this->admin)->get(route('admin.members.index'))->assertOk();
        $this->assertTrue($normal->viewData('members')->contains('id', $visible->id));
        $this->assertFalse($normal->viewData('members')->contains('id', $erased->id));

        $erasedView = $this->actingAs($this->admin)->get(route('admin.members.index', ['erased' => '1']))->assertOk();
        $this->assertFalse($erasedView->viewData('members')->contains('id', $visible->id));
        $this->assertTrue($erasedView->viewData('members')->contains('id', $erased->id));
    }

    public function test_bureau_master_can_purge_an_erased_member(): void
    {
        $erased = $this->erasedMemberUser();

        $this->actingAs($this->admin)
            ->delete(route('admin.trash.force-delete', ['kind' => 'members', 'id' => $erased->id]))
            ->assertRedirect();

        $this->assertFalse(User::withTrashed()->whereKey($erased->id)->exists());
    }

    public function test_bureau_finance_cannot_purge_an_erased_member(): void
    {
        $finance = $this->createBureauUser();
        $finance->syncRoles(['bureau_finance']);

        $erased = $this->erasedMemberUser();

        $this->actingAs($finance)
            ->delete(route('admin.trash.force-delete', ['kind' => 'members', 'id' => $erased->id]))
            ->assertForbidden();

        $this->assertTrue(User::withTrashed()->whereKey($erased->id)->exists());
    }

    private function erasedMemberUser(): User
    {
        $user = $this->createMemberUser();
        $user->detail->update(['first_name' => 'ERASED', 'last_name' => 'ERASED']);
        $user->update(['primary_email' => "erased-{$user->id}@erased.local"]);
        $user->detail->delete();
        $user->delete();

        return $user;
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
