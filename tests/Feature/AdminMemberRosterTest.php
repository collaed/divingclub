<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\StatusSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class AdminMemberRosterTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole('bureau_master');
        MemberDetail::create(['user_id' => $this->admin->id, 'first_name' => 'A', 'last_name' => 'B']);
    }

    private function memberWithStatus(string $slug): User
    {
        $status = MemberStatus::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);
        $u = User::factory()->create(['status_id' => $status->id]);
        MemberDetail::create(['user_id' => $u->id, 'first_name' => ucfirst($slug), 'last_name' => 'Member']);

        return $u;
    }

    public function test_default_listing_hides_former_members(): void
    {
        $active = $this->memberWithStatus('actif');
        $former = $this->memberWithStatus('former');

        $this->actingAs($this->admin)->get(route('admin.members.index'))
            ->assertOk()
            ->assertSee($active->primary_email)
            ->assertDontSee($former->primary_email);
    }

    public function test_historic_toggle_shows_former_members(): void
    {
        $former = $this->memberWithStatus('former');

        $this->actingAs($this->admin)->get(route('admin.members.index', ['historic' => 1]))
            ->assertOk()
            ->assertSee($former->primary_email);
    }

    public function test_ajax_status_update_persists_and_returns_json(): void
    {
        $set = StatusSet::create(['name' => 'Externe', 'slug' => 'externe']);
        $actif = MemberStatus::firstOrCreate(['slug' => 'actif'], ['name' => 'Actif']);
        $set->statuses()->attach($actif->id);
        $member = $this->memberWithStatus('actif');

        $this->actingAs($this->admin)
            ->patchJson(route('admin.members.status.update', $member), ['status_set_id' => $set->id, 'status_id' => $actif->id])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $member->refresh();
        $this->assertSame($set->id, $member->status_set_id);
        $this->assertSame($actif->id, $member->status_id);
    }

    public function test_ajax_status_out_of_set_is_rejected(): void
    {
        $set = StatusSet::create(['name' => 'Externe', 'slug' => 'externe']);
        $actif = MemberStatus::firstOrCreate(['slug' => 'actif'], ['name' => 'Actif']);
        $set->statuses()->attach($actif->id);
        $honoraire = MemberStatus::firstOrCreate(['slug' => 'honoraire'], ['name' => 'Honoraire']);
        $member = $this->memberWithStatus('actif');

        $this->actingAs($this->admin)
            ->patchJson(route('admin.members.status.update', $member), ['status_set_id' => $set->id, 'status_id' => $honoraire->id])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_non_bureau_cannot_access_roster_status_update(): void
    {
        $member = $this->memberWithStatus('actif');
        $actor = User::factory()->create();
        $actor->assignRole('member');

        $this->actingAs($actor)
            ->patchJson(route('admin.members.status.update', $member), ['status_id' => 1])
            ->assertForbidden();
    }
}
