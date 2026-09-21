<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Federation;
use App\Models\MemberDetail;
use App\Models\MembershipFeeComponent;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\Season;
use App\Models\User;
use App\Services\MembershipRenewalService;
use Database\Seeders\Fee2027Seeder;
use Database\Seeders\MemberStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class MembershipRenewalTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(MemberStatusSeeder::class);
        Season::factory()->create(['year' => '2027', 'start_date' => '2026-09-01', 'fee_taper_tiers' => null]);
        $this->seed(Fee2027Seeder::class);

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole('bureau_master');
        MemberDetail::create(['user_id' => $this->admin->id, 'first_name' => 'Bureau', 'last_name' => 'Admin', 'cotisation_years' => ['2027']]);
    }

    private function member(string $status, array $years = ['2026'], string $dob = '1980-01-01', string $last = 'Dupont'): User
    {
        $s = MemberStatus::where('slug', $status)->firstOrFail();
        $u = User::factory()->create(['status_id' => $s->id, 'email_verified_at' => now()]);
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Marie', 'last_name' => $last, 'date_of_birth' => $dob, 'cotisation_years' => $years]);

        return $u->fresh(['detail', 'status']);
    }

    private function received(User $user, array $data = []): TestResponse
    {
        return $this->actingAs($this->admin)->postJson(route('admin.payments.renewals.received', $user), ['season_year' => '2027'] + $data);
    }

    public function test_only_current_non_honoraire_members_who_have_not_paid_the_season_are_listed(): void
    {
        $owes = $this->member('externe', ['2026']);
        $paid = $this->member('externe', ['2026', '2027'], last: 'Paid');
        $honoraire = $this->member('honoraire', ['2026'], last: 'Honoraire');
        $former = $this->member('former', ['2020'], last: 'Former');

        $ids = app(MembershipRenewalService::class)->outstanding('2027')->pluck('id');

        $this->assertTrue($ids->contains($owes->id));
        $this->assertFalse($ids->contains($paid->id));
        $this->assertFalse($ids->contains($honoraire->id));
        $this->assertFalse($ids->contains($former->id));
    }

    public function test_the_screen_proposes_the_status_price_and_shows_the_received_button(): void
    {
        $this->member('externe');

        $this->actingAs($this->admin)->get(route('admin.payments.renewals'))
            ->assertOk()
            ->assertSee('190.00') // 130 + 50 licence + 10 FLASSA
            ->assertSee('Received €190.00');
    }

    public function test_a_commitment_is_the_amount_proposed_by_default(): void
    {
        $user = $this->member('externe');
        PaymentExpected::create([
            'user_id' => $user->id, 'type' => 'membership', 'season_year' => '2027',
            'amount_due' => 215, 'components' => ['membership' => 130, 'ass_loisir1' => 25], 'status' => 'pending',
        ]);

        $this->actingAs($this->admin)->get(route('admin.payments.renewals'))
            ->assertOk()->assertSee('215.00')->assertSee('They committed to this amount');
    }

    public function test_last_seasons_insurance_is_kept_in_the_proposal(): void
    {
        $user = $this->member('externe');
        $federation = Federation::create(['acronym' => 'FFESSM', 'full_name' => 'Fédération', 'visibility' => 'active']);
        $user->licences()->create(['federation_id' => $federation->id, 'insurance_type' => 'Loisir 1 Top', 'season' => '2025-2026']);

        $proposal = app(MembershipRenewalService::class)->proposal($user->fresh('licences'), '2027');

        $this->assertSame('ass_loisir1top', $proposal['insurance']);
        $this->assertEqualsWithDelta(238.0, $proposal['amount'], 0.001); // 190 + 48
    }

    public function test_received_marks_the_proposed_amount_paid_and_the_season_as_paid(): void
    {
        $user = $this->member('externe');

        $this->received($user)->assertOk()->assertJson(['ok' => true]);

        $this->assertContains('2027', $user->detail->fresh()->cotisation_years);
        $this->assertDatabaseHas('payment_expected', [
            'user_id' => $user->id, 'season_year' => '2027', 'status' => 'paid', 'amount_paid' => 190,
        ]);
        $this->assertSame(0, app(MembershipRenewalService::class)->outstanding('2027')->where('id', $user->id)->count());
    }

    public function test_a_typed_amount_that_adds_one_insurance_tier_is_recognised(): void
    {
        $user = $this->member('externe');

        $this->received($user, ['amount' => 215])->assertOk();

        $payment = PaymentExpected::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('paid', $payment->status);
        $this->assertArrayHasKey('ass_loisir1', $payment->components);
        $this->assertContains('2027', $user->detail->fresh()->cotisation_years);
    }

    public function test_an_amount_that_matches_nothing_is_refused_and_nothing_is_marked(): void
    {
        $user = $this->member('externe');

        $this->received($user, ['amount' => 200])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'ambiguous' => false]);

        $this->assertNotContains('2027', $user->detail->fresh()->cotisation_years);
        $this->assertDatabaseMissing('payment_expected', ['user_id' => $user->id, 'status' => 'paid']);
    }

    public function test_an_amount_matching_several_options_asks_which_one(): void
    {
        MembershipFeeComponent::create([
            'season_id' => MembershipFeeComponent::where('slug', 'ass_loisir1')->value('season_id'),
            'name' => 'Same price as Loisir 1', 'slug' => 'ass_twin', 'kind' => MembershipFeeComponent::KIND_ASSURANCE,
            'amount' => 25, 'is_optional' => true, 'sort_order' => 99,
        ]);
        $user = $this->member('externe');

        $this->received($user, ['amount' => 215])->assertStatus(422)->assertJson(['ambiguous' => true]);
        $this->assertNotContains('2027', $user->detail->fresh()->cotisation_years);

        $this->received($user, ['amount' => 215, 'insurance' => 'ass_twin'])->assertOk();
        $this->assertArrayHasKey('ass_twin', PaymentExpected::where('user_id', $user->id)->firstOrFail()->components);
    }

    public function test_honoraires_are_marked_paid_from_the_first_of_october_only(): void
    {
        $honoraire = $this->member('honoraire', ['2026']);
        $svc = app(MembershipRenewalService::class);

        $this->assertSame(0, $svc->markHonorairesPaid('2027', Carbon::create(2026, 9, 30)));
        $this->assertNotContains('2027', $honoraire->detail->fresh()->cotisation_years);

        $this->assertSame(1, $svc->markHonorairesPaid('2027', Carbon::create(2026, 10, 1)));
        $this->assertContains('2027', $honoraire->detail->fresh()->cotisation_years);
        $this->assertSame(0, $svc->markHonorairesPaid('2027', Carbon::create(2026, 10, 2)));
    }

    public function test_a_reconciled_membership_payment_marks_the_season_as_paid(): void
    {
        $user = $this->member('externe');
        $payment = PaymentExpected::create([
            'user_id' => $user->id, 'type' => 'membership', 'season_year' => '2027',
            'amount_due' => 190, 'amount_paid' => 190, 'components' => [], 'status' => 'paid',
        ]);

        app(MembershipRenewalService::class)->recordSeasonPaid($payment);

        $this->assertContains('2027', $user->detail->fresh()->cotisation_years);
    }

    public function test_a_sympathisant_pays_the_nominal_amount_even_for_a_child(): void
    {
        $child = $this->member('sympathisant', dob: '2016-01-01');

        $this->assertEqualsWithDelta(30.0, app(MembershipRenewalService::class)->proposal($child, '2027')['amount'], 0.001);
    }

    public function test_the_bureau_can_mark_a_member_paid_by_cash_for_any_amount(): void
    {
        $user = $this->member('externe');

        $this->actingAs($this->admin)->postJson(route('admin.payments.renewals.override', $user), [
            'season_year' => '2027', 'method' => 'cash', 'amount' => 150, 'note' => 'Paid at the pool, agreed a reduction',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertContains('2027', $user->detail->fresh()->cotisation_years);
        $this->assertDatabaseHas('payment_expected', [
            'user_id' => $user->id, 'season_year' => '2027', 'status' => 'paid',
            'amount_due' => 190, 'amount_paid' => 150, 'payment_method' => 'cash', 'note' => 'Paid at the pool, agreed a reduction',
        ]);
    }

    public function test_an_override_without_an_amount_records_the_expected_amount(): void
    {
        $user = $this->member('externe');

        $this->actingAs($this->admin)->postJson(route('admin.payments.renewals.override', $user), ['season_year' => '2027', 'method' => 'other'])->assertOk();

        $this->assertDatabaseHas('payment_expected', ['user_id' => $user->id, 'amount_paid' => 190, 'payment_method' => 'other']);
    }

    public function test_an_override_needs_a_known_payment_method(): void
    {
        $user = $this->member('externe');

        $this->actingAs($this->admin)->postJson(route('admin.payments.renewals.override', $user), ['season_year' => '2027', 'method' => 'bitcoin'])->assertStatus(422);
        $this->assertNotContains('2027', $user->detail->fresh()->cotisation_years);
    }

    public function test_the_insurance_queue_lists_paid_insurance_in_payment_order_and_hides_registered_ones(): void
    {
        $first = $this->member('externe', last: 'First');
        $second = $this->member('externe', last: 'Second');
        $none = $this->member('externe', last: 'NoInsurance');
        $this->received($second, ['amount' => 205])->assertOk(); // Loisir 1
        $this->received($first, ['amount' => 238])->assertOk();  // Loisir 1 Top
        $this->received($none)->assertOk();                      // no insurance
        PaymentExpected::where('user_id', $second->id)->update(['paid_at' => '2026-09-01']);
        PaymentExpected::where('user_id', $first->id)->update(['paid_at' => '2026-09-05']);

        $queue = app(MembershipRenewalService::class)->insuranceQueue('2027');

        $this->assertSame([$second->id, $first->id], $queue->pluck('payment.user_id')->all());

        $this->actingAs($this->admin)->postJson(route('admin.payments.insurance.registered', $queue->first()['payment']), ['registered' => true])
            ->assertOk()->assertJson(['registered' => true]);

        $this->assertSame([$first->id], app(MembershipRenewalService::class)->insuranceQueue('2027')->pluck('payment.user_id')->all());
        $this->assertCount(2, app(MembershipRenewalService::class)->insuranceQueue('2027', includeRegistered: true));
        $this->actingAs($this->admin)->get(route('admin.payments.insurance'))->assertOk()->assertSee('Assurance Loisir 1 Top');
    }

    public function test_next_seasons_proposal_starts_from_the_insurance_paid_last_season(): void
    {
        $user = $this->member('externe');
        PaymentExpected::create([
            'user_id' => $user->id, 'type' => 'membership', 'season_year' => '2026',
            'amount_due' => 200, 'amount_paid' => 200, 'components' => ['membership' => 130, 'ass_loisir2' => 30], 'status' => 'paid',
        ]);

        $proposal = app(MembershipRenewalService::class)->proposal($user, '2027');

        $this->assertSame('ass_loisir2', $proposal['insurance']);
        $this->assertEqualsWithDelta(220.0, $proposal['amount'], 0.001); // 190 + 30
    }
}
