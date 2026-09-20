<?php

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class AdminPaymentTest extends TestCase
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
    }

    public function test_bureau_can_access_payments(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.payments.index'))
            ->assertOk();
    }

    public function test_bureau_can_generate_fee(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        MemberDetail::factory()->create(['user_id' => $member->id, 'first_name' => 'Jean', 'last_name' => 'Dupont']);
        $member->assignRole('member');

        $this->actingAs($this->admin)
            ->post(route('admin.payments.generate', $member))
            ->assertRedirect();

        $this->assertTrue(PaymentExpected::where('user_id', $member->id)->exists());
    }

    public function test_bureau_can_access_reconciliation(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.payments.reconciliation'))
            ->assertOk();
    }

    public function test_bulk_fee_generation_bills_real_member_statuses_not_just_the_literal_actif_slug(): void
    {
        $realStatus = MemberStatus::firstOrCreate(['slug' => 'membre_de_droit'], ['name' => 'Membre de droit']);
        $member = User::factory()->create(['email_verified_at' => now(), 'status_id' => $realStatus->id]);
        MemberDetail::factory()->create(['user_id' => $member->id]);
        $member->assignRole('member');

        $this->actingAs($this->admin)->post(route('admin.payments.generate-bulk'), ['season' => '2027']);

        $this->assertTrue(PaymentExpected::where('user_id', $member->id)->where('season_year', '2027')->exists());
    }

    public function test_bulk_fee_generation_skips_former_and_honoraire_members(): void
    {
        $former = MemberStatus::firstOrCreate(['slug' => 'former'], ['name' => 'Ancien membre']);
        $honoraire = MemberStatus::firstOrCreate(['slug' => 'honoraire'], ['name' => 'Honoraire']);

        $formerMember = User::factory()->create(['email_verified_at' => now(), 'status_id' => $former->id]);
        MemberDetail::factory()->create(['user_id' => $formerMember->id]);
        $formerMember->assignRole('member');

        $honoraireMember = User::factory()->create(['email_verified_at' => now(), 'status_id' => $honoraire->id]);
        MemberDetail::factory()->create(['user_id' => $honoraireMember->id]);
        $honoraireMember->assignRole('member');

        $this->actingAs($this->admin)->post(route('admin.payments.generate-bulk'), ['season' => '2027']);

        $this->assertFalse(PaymentExpected::where('user_id', $formerMember->id)->exists());
        $this->assertFalse(PaymentExpected::where('user_id', $honoraireMember->id)->exists());
    }
}
