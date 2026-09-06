<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\MembershipFee;
use App\Models\MemberStatus;
use App\Models\StatusSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class AdminStatusDeleteTest extends TestCase
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

    private function makeStatus(string $slug): MemberStatus
    {
        return MemberStatus::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);
    }

    public function test_bureau_can_delete_unused_status(): void
    {
        $status = $this->makeStatus('temporary');

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.settings.status.destroy', $status))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('member_statuses', ['id' => $status->id]);
    }

    public function test_delete_detaches_status_from_sets(): void
    {
        $status = $this->makeStatus('temporary');
        $set = StatusSet::create(['name' => 'Externe', 'slug' => 'externe']);
        $set->statuses()->attach($status->id);

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.settings.status.destroy', $status))
            ->assertOk();

        $this->assertDatabaseMissing('status_set_members', ['member_status_id' => $status->id]);
    }

    public function test_cannot_delete_status_with_members(): void
    {
        $status = $this->makeStatus('inuse');
        $member = User::factory()->create(['status_id' => $status->id]);
        MemberDetail::create(['user_id' => $member->id, 'first_name' => 'M', 'last_name' => 'N']);

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.settings.status.destroy', $status))
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertDatabaseHas('member_statuses', ['id' => $status->id]);
    }

    public function test_cannot_delete_status_with_membership_fee(): void
    {
        $status = $this->makeStatus('priced');
        MembershipFee::create(['season_year' => '2027', 'status_id' => $status->id, 'amount' => 55]);

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.settings.status.destroy', $status))
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertDatabaseHas('member_statuses', ['id' => $status->id]);
    }

    public function test_cannot_delete_protected_lifecycle_status(): void
    {
        $former = $this->makeStatus('former');

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.settings.status.destroy', $former))
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertDatabaseHas('member_statuses', ['id' => $former->id]);
    }

    public function test_deleted_status_disappears_from_dues_selector(): void
    {
        $status = $this->makeStatus('ephemeral');

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.settings.status.destroy', $status))
            ->assertOk();

        $this->get(route('dues.show'))
            ->assertOk()
            ->assertDontSee('Ephemeral');
    }
}
