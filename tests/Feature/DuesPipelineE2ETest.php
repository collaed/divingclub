<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\MembershipFee;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\Season;
use App\Models\StatusSet;
use App\Models\User;
use App\Services\MailAliasService;
use Database\Seeders\MemberStatusSeeder;
use Database\Seeders\StatusSetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * End-to-end: bureau configures sets + component tapers, classifies a member
 * via the roster, the member computes and commits dues on /dues, the
 * PaymentExpected is written, and inactive members are excluded from mail.
 */
class DuesPipelineE2ETest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(MemberStatusSeeder::class);
        $this->seed(StatusSetSeeder::class);

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole('bureau_master');
        MemberDetail::create(['user_id' => $this->admin->id, 'first_name' => 'Admin', 'last_name' => 'User']);
    }

    public function test_full_dues_pipeline(): void
    {
        // 1. Bureau configures the 2027 season + the Externe fee (a child is an Externe
        //    member who pays the under-18 share of it).
        Season::factory()->create(['year' => '2027', 'start_date' => '2026-09-01']);
        $externe = MemberStatus::where('slug', 'externe')->firstOrFail();
        MembershipFee::create(['season_year' => '2027', 'status_id' => $externe->id, 'amount' => 110]);

        // 2. Bureau adds a FLASSA licence component, free under 18 at the 2027 anchor.
        $this->actingAs($this->admin)->post(route('admin.payments.component.store'), [
            'name' => 'Licence FLASSA', 'slug' => 'flassa', 'amount' => 40, 'is_optional' => '1',
            'taper_below_age' => 18, 'taper_ratio' => 0, 'age_anchor_date' => '2027-01-01',
        ])->assertRedirect();

        // 3. A new member self-registers (unclassified) with a DOB making them a minor.
        $member = User::factory()->create(['status_id' => null, 'status_set_id' => null, 'email_verified_at' => now()]);
        MemberDetail::create(['user_id' => $member->id, 'first_name' => 'Petit', 'last_name' => 'Plongeur', 'date_of_birth' => '2012-05-05']);
        $member->assignRole('member');

        // 4. Bureau classifies them into the Externe set with the Externe status via the roster (AJAX).
        $externeSet = StatusSet::where('slug', 'externe')->firstOrFail();
        $this->actingAs($this->admin)
            ->patchJson(route('admin.members.status.update', $member), [
                'status_set_id' => $externeSet->id,
                'status_id' => $externe->id,
            ])->assertOk()->assertJson(['ok' => true]);

        $member->refresh();
        $this->assertSame($externeSet->id, $member->status_set_id);
        $this->assertSame($externe->id, $member->status_id);

        // 5. Member loads /dues — sees only in-set statuses (Externe, Actif, ...) not Fonctionnaire.
        $this->actingAs($member)->get(route('dues.show', ['season_year' => 2027]))
            ->assertOk()
            ->assertSee('Externe')
            ->assertDontSee('Fonctionnaire');

        // 6. Member calculates: base 55 (under 18: half of 110) + FLASSA tapered to 0 = 55.
        $this->actingAs($member)->post(route('dues.calculate'), [
            'season_year' => '2027', 'status_id' => $externe->id,
            'last_name' => 'Plongeur', 'first_name' => 'Petit',
            'optionals' => ['flassa'],
        ])->assertOk()->assertSee('€55.00');

        // 7. Member commits — classified now, so NOT provisional.
        $this->actingAs($member)->post(route('dues.commit'), [
            'season_year' => '2027', 'status_id' => $externe->id, 'optionals' => ['flassa'],
        ])->assertRedirect();

        $pe = PaymentExpected::where('user_id', $member->id)->where('type', 'membership')->firstOrFail();
        $this->assertFalse($pe->provisional);
        $this->assertSame('55.00', number_format((float) $pe->amount_due, 2));
        $this->assertSame('pending', $pe->status);

        // 8. A former member is excluded from the "all members" mail; the child is included.
        $former = MemberStatus::where('slug', 'former')->firstOrFail();
        $lapsed = User::factory()->create(['status_id' => $former->id, 'primary_email' => 'lapsed@club.eu', 'email_verified_at' => now()]);
        MemberDetail::create(['user_id' => $lapsed->id, 'first_name' => 'Old', 'last_name' => 'Timer']);

        $resolved = MailAliasService::resolve('members@clubcep.eu');
        $this->assertContains($member->primary_email, $resolved['emails']);
        $this->assertNotContains('lapsed@club.eu', $resolved['emails']);
    }
}
