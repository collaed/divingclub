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

    public function test_saving_an_unconfirmed_members_info_without_picking_a_status_does_not_approve_them(): void
    {
        $member = $this->member();
        $member->update(['status_id' => null]);

        $this->actingAs($this->admin)->post(route('admin.profile.update.info', $member), [
            'first_name' => 'Mnew', 'last_name' => 'N', 'sex' => 'M',
            // No status_id at all — mirrors the blank placeholder option in
            // profile/tabs/info.blade.php, not the browser silently defaulting
            // to whichever status happens to render first.
        ])->assertRedirect();

        $member->refresh();
        $this->assertNull($member->status_id);
        $this->assertSame('Mnew', $member->detail->first_name);
    }

    public function test_bureau_can_deliberately_approve_a_pending_member_by_choosing_a_status(): void
    {
        $actifId = MemberStatus::firstOrCreate(['slug' => 'actif'], ['name' => 'Actif'])->id;
        $member = $this->member();
        $member->update(['status_id' => null]);

        $this->actingAs($this->admin)->post(route('admin.profile.update.info', $member), [
            'first_name' => 'M', 'last_name' => 'N', 'sex' => 'M',
            'status_id' => $actifId,
        ])->assertRedirect();

        $this->assertSame($actifId, $member->refresh()->status_id);
    }

    public function test_the_status_select_has_no_selected_option_for_an_unconfirmed_member(): void
    {
        $member = $this->member();
        $member->update(['status_id' => null]);

        $response = $this->actingAs($this->admin)->get(route('admin.profile.show', $member))->assertOk();

        // Without the blank placeholder, the browser would silently default to
        // the first real <option>, and any unrelated save would submit it —
        // approving the member. Assert the raw HTML has no non-blank selected option.
        $response->assertSee(__('— Not yet confirmed —'));
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="\d+" selected>/',
            $response->getContent()
        );
    }

    public function test_the_status_select_has_no_blank_placeholder_for_a_confirmed_member(): void
    {
        $actifId = MemberStatus::firstOrCreate(['slug' => 'actif'], ['name' => 'Actif'])->id;
        $member = $this->member();
        $member->update(['status_id' => $actifId]);

        $response = $this->actingAs($this->admin)->get(route('admin.profile.show', $member))->assertOk();

        $response->assertDontSee(__('— Not yet confirmed —'));
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
