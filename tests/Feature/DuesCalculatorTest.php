<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\MembershipFee;
use App\Models\MembershipFeeComponent;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\Season;
use App\Models\StatusSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class DuesCalculatorTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function status(string $slug): MemberStatus
    {
        return MemberStatus::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);
    }

    private function member(?StatusSet $set = null, ?MemberStatus $status = null): User
    {
        $u = User::factory()->create([
            'status_id' => $status?->id,
            'status_set_id' => $set?->id,
            'email_verified_at' => now(),
        ]);
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Jean', 'last_name' => 'Dupont']);
        $u->assignRole('member');

        return $u->fresh();
    }

    public function test_guest_sees_calculator(): void
    {
        $this->get(route('dues.show'))->assertOk()->assertSee('Membership Dues Calculator');
    }

    public function test_classified_member_only_sees_in_set_statuses(): void
    {
        $externe = StatusSet::create(['name' => 'Externe', 'slug' => 'externe']);
        $actif = $this->status('actif');
        $externe->statuses()->attach($actif->id);

        $member = $this->member($externe, $actif);

        $res = $this->actingAs($member)->get(route('dues.show'))->assertOk();
        $res->assertSee('Actif');
        $res->assertDontSee('Fonctionnaire');
    }

    public function test_unclassified_member_sees_all_and_can_commit_provisionally(): void
    {
        $actif = $this->status('actif');
        MembershipFee::create(['season_year' => '2027', 'status_id' => $actif->id, 'amount' => 110]);
        $member = $this->member(null, null); // no set, no status

        $this->actingAs($member)->get(route('dues.show', ['season_year' => 2027]))
            ->assertOk()
            ->assertSee('category has not been assigned');

        $this->actingAs($member)->post(route('dues.commit'), [
            'season_year' => '2027',
            'status_id' => $actif->id,
        ])->assertRedirect();

        $pe = PaymentExpected::where('user_id', $member->id)->where('type', 'membership')->first();
        $this->assertNotNull($pe);
        $this->assertTrue($pe->provisional);
        $this->assertSame('110.00', number_format((float) $pe->amount_due, 2));

        // Commitment must NOT change the profile.
        $member->refresh();
        $this->assertNull($member->status_id);
        $this->assertNull($member->status_set_id);
    }

    public function test_classified_member_commit_is_not_provisional(): void
    {
        $set = StatusSet::create(['name' => 'Externe', 'slug' => 'externe']);
        $actif = $this->status('actif');
        $set->statuses()->attach($actif->id);
        MembershipFee::create(['season_year' => '2027', 'status_id' => $actif->id, 'amount' => 110]);
        $member = $this->member($set, $actif);

        $this->actingAs($member)->post(route('dues.commit'), [
            'season_year' => '2027',
            'status_id' => $actif->id,
        ])->assertRedirect();

        $pe = PaymentExpected::where('user_id', $member->id)->first();
        $this->assertFalse($pe->provisional);
    }

    public function test_calculate_applies_component_age_taper(): void
    {
        $junior = $this->status('junior');
        MembershipFee::create(['season_year' => '2027', 'status_id' => $junior->id, 'amount' => 55]);
        MembershipFeeComponent::create([
            'name' => 'Licence FLASSA', 'slug' => 'flassa', 'amount' => 40, 'is_optional' => true,
            'taper_below_age' => 18, 'taper_ratio' => 0, 'age_anchor_date' => '2027-01-01',
        ]);

        $member = $this->member(null, $junior);
        $member->detail->update(['date_of_birth' => '2012-05-05']);

        $res = $this->actingAs($member)->post(route('dues.calculate'), [
            'season_year' => '2027',
            'status_id' => $junior->id,
            'last_name' => 'Dupont', 'first_name' => 'Jean',
            'optionals' => ['flassa'],
        ])->assertOk();

        // Base 55 + FLASSA tapered to 0 = 55.
        $res->assertSee('€55.00');
    }

    public function test_guest_cannot_commit(): void
    {
        $this->post(route('dues.commit'), ['season_year' => '2027', 'status_id' => 1])
            ->assertRedirect(route('login'));
    }

    public function test_former_status_is_not_offered_on_the_calculator(): void
    {
        $this->status('actif');
        $this->status('former');

        $res = $this->get(route('dues.show'))->assertOk();
        $res->assertSee('Actif');
        $res->assertDontSee('Ancien membre');
        $res->assertDontSee('>Former<', false);
    }

    public function test_former_status_commit_is_rejected(): void
    {
        $former = $this->status('former');
        $member = $this->member(null, null);

        $this->actingAs($member)->post(route('dues.commit'), [
            'season_year' => '2027',
            'status_id' => $former->id,
        ])->assertSessionHasErrors('status_id');

        $this->assertNull(PaymentExpected::where('user_id', $member->id)->first());
    }

    public function test_fees_fall_back_to_last_good_year(): void
    {
        $actif = $this->status('actif');
        // Only a 2026 fee exists; requesting 2027 should reuse it.
        MembershipFee::create(['season_year' => '2026', 'status_id' => $actif->id, 'amount' => 105]);

        $res = $this->get(route('dues.show', ['season_year' => 2027]))->assertOk();
        $res->assertSee('105.00');
    }

    public function test_season_year_auto_resolves_from_cutoff(): void
    {
        // A season starting in September makes the dues year roll to next year.
        Season::factory()->create(['year' => '2026', 'start_date' => '2026-09-15', 'end_date' => '2027-07-15']);
        Carbon::setTestNow('2026-10-01');

        $this->assertSame('2027', Season::currentDuesYear());

        Carbon::setTestNow();
    }
}
