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

class ProfileStatusSetTest extends TestCase
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

    private function setWithStatuses(string $slug, array $statusSlugs): StatusSet
    {
        $set = StatusSet::create(['name' => ucfirst($slug), 'slug' => $slug]);
        foreach ($statusSlugs as $ss) {
            $status = MemberStatus::firstOrCreate(['slug' => $ss], ['name' => ucfirst($ss)]);
            $set->statuses()->attach($status->id);
        }

        return $set;
    }

    private function member(): User
    {
        $u = User::factory()->create();
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'M', 'last_name' => 'N']);
        $u->assignRole('member');

        return $u;
    }

    public function test_bureau_can_assign_status_set_and_in_set_status(): void
    {
        $set = $this->setWithStatuses('externe', ['actif', 'sympathisant']);
        $actifId = MemberStatus::where('slug', 'actif')->value('id');
        $member = $this->member();

        $this->actingAs($this->admin)->post(route('admin.profile.update.info', $member), [
            'first_name' => 'M', 'last_name' => 'N', 'sex' => 'M',
            'status_set_id' => $set->id,
            'status_id' => $actifId,
        ])->assertRedirect();

        $member->refresh();
        $this->assertSame($set->id, $member->status_set_id);
        $this->assertSame($actifId, $member->status_id);
    }

    public function test_out_of_set_status_is_rejected(): void
    {
        $set = $this->setWithStatuses('externe', ['actif', 'sympathisant']);
        $honoraireId = MemberStatus::firstOrCreate(['slug' => 'honoraire'], ['name' => 'Honoraire'])->id;
        $member = $this->member();

        $this->actingAs($this->admin)->post(route('admin.profile.update.info', $member), [
            'first_name' => 'M', 'last_name' => 'N', 'sex' => 'M',
            'status_set_id' => $set->id,
            'status_id' => $honoraireId,
        ])->assertSessionHasErrors('status_id');

        $member->refresh();
        $this->assertNull($member->status_set_id);
    }

    public function test_non_bureau_cannot_change_status_set(): void
    {
        $set = $this->setWithStatuses('externe', ['actif']);
        $member = $this->member();

        $this->actingAs($member)->post(route('profile.update.info'), [
            'first_name' => 'M', 'last_name' => 'N', 'sex' => 'M',
            'status_set_id' => $set->id,
        ])->assertRedirect();

        $member->refresh();
        $this->assertNull($member->status_set_id);
    }

    public function test_bureau_can_create_status_set(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.status-set.store'), [
            'name' => 'Jeune', 'slug' => 'jeune',
        ])->assertRedirect();

        $this->assertNotNull(StatusSet::where('slug', 'jeune')->first());
    }

    public function test_bureau_can_sync_statuses_into_set_via_ajax(): void
    {
        $set = StatusSet::create(['name' => 'Jeune', 'slug' => 'jeune']);
        $junior = MemberStatus::firstOrCreate(['slug' => 'junior'], ['name' => 'Junior']);
        $enfant = MemberStatus::firstOrCreate(['slug' => 'enfant'], ['name' => 'Enfant']);

        $this->actingAs($this->admin)->patchJson(route('admin.settings.status-set.update', $set), [
            'statuses' => [$junior->id, $enfant->id],
            'default_status_id' => $junior->id,
        ])->assertOk()->assertJson(['ok' => true]);

        $set->refresh()->load('statuses');
        $this->assertCount(2, $set->statuses);
        $this->assertSame('junior', $set->defaultStatus()?->slug);
    }
}
