<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Club Partnerships and the Dive Group Planner are gated behind their own
 * permissions (manage partnerships / manage dive groups), bureau_master only
 * for now — see the 2026_09_11_090000 migration.
 */
class UnreleasedFeaturePermissionsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function bureauFinanceUser(): User
    {
        $user = $this->createBureauUser();
        $user->syncRoles(['bureau_finance']);

        return $user;
    }

    public function test_only_bureau_master_can_reach_partnerships(): void
    {
        $this->actingAs($this->createBureauUser())->get(route('admin.partnerships.index'))->assertOk();
        $this->actingAs($this->bureauFinanceUser())->get(route('admin.partnerships.index'))->assertForbidden();
    }

    public function test_admin_dashboard_only_links_to_partnerships_for_bureau_master(): void
    {
        $master = $this->createBureauUser();
        $finance = $this->bureauFinanceUser();

        $this->actingAs($master)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($finance)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.partnerships.registrations'), false);
    }

    public function test_only_bureau_master_can_reach_the_dive_group_planner(): void
    {
        $event = Event::factory()->create(['event_type' => 'dive']);

        $this->actingAs($this->createBureauUser())->get(route('events.dive-groups', $event))->assertOk();
        $this->actingAs($this->bureauFinanceUser())->get(route('events.dive-groups', $event))->assertForbidden();
    }

    public function test_event_page_only_shows_the_group_planner_card_to_bureau_master(): void
    {
        $event = Event::factory()->create(['event_type' => 'dive']);
        $member = User::factory()->create(['email_verified_at' => now()]);
        MemberDetail::create(['user_id' => $member->id, 'first_name' => 'T', 'last_name' => 'U']);
        $member->assignRole('member');

        $this->actingAs($this->createBureauUser())->get(route('events.show', $event))
            ->assertOk()->assertSee(__('Open Group Planner'));
        $this->actingAs($member)->get(route('events.show', $event))
            ->assertOk()->assertDontSee(__('Open Group Planner'));
    }
}
