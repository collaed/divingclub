<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Federation;
use App\Models\MemberDetail;
use App\Models\PaymentExpected;
use App\Models\Season;
use App\Models\User;
use Database\Seeders\Fee2027Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * The "money received, from whom, what's still outstanding" dashboard:
 * one row per paid membership this season, with its medical cert status,
 * any pending FFESSM/FLASSA licence renewal, and whether a purchased
 * insurance tier still needs registering with the insurer.
 */
class PaymentActionChecklistTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Season::factory()->create(['year' => '2027', 'start_date' => '2026-09-01', 'fee_taper_tiers' => null]);
        $this->seed(Fee2027Seeder::class);
        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole('bureau_master');
        MemberDetail::create(['user_id' => $this->admin->id, 'first_name' => 'Bureau', 'last_name' => 'Admin']);
    }

    private function member(string $last): User
    {
        $u = User::factory()->create();
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Marie', 'last_name' => $last]);

        return $u;
    }

    private function paidMembership(User $user, array $components, array $extra = []): PaymentExpected
    {
        return PaymentExpected::create([
            'user_id' => $user->id, 'type' => 'membership', 'season_year' => '2027',
            'amount_due' => 100, 'amount_paid' => 100, 'status' => 'paid', 'paid_at' => now(),
            'components' => $components,
        ] + $extra);
    }

    public function test_a_paid_member_with_outstanding_items_is_listed_with_them(): void
    {
        $ffessm = Federation::create(['acronym' => 'FFESSM', 'full_name' => 'Fédération', 'visibility' => 'active']);
        $user = $this->member('Dupont');
        $user->licences()->create(['federation_id' => $ffessm->id, 'season' => '2027', 'licence_request_pending' => true]);
        $this->paidMembership($user, ['ass_loisir1top' => 48.0]);

        $response = $this->actingAs($this->admin)->get(route('admin.payments.checklist', ['season_year' => '2027']))->assertOk();

        $response->assertSee('Dupont');
        $response->assertSee('No Certificate');
        $response->assertSee('FFESSM');
        $response->assertSee('Assurance Loisir 1 Top');
    }

    public function test_a_current_medical_cert_shows_its_exam_date_and_a_named_download_link(): void
    {
        $user = $this->member('Lambert');
        $this->paidMembership($user, []);
        $cert = Document::create([
            'user_id' => $user->id, 'category' => 'medical', 'is_current' => true,
            'date_established' => '2026-11-02', 'expiry_date' => '2027-06-15', 'file_path' => 'documents/current.pdf',
            'original_filename' => 'scan.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.payments.checklist', ['season_year' => '2027', 'all' => 1]))->assertOk();

        $response->assertSee('02/11/2026');
        $response->assertDontSee('15/06/2027');
        $response->assertSee(route('profile.document.download', ['document' => $cert, 'as' => 'Lambert_Marie_'.$user->id]), false);
    }

    public function test_a_fully_resolved_member_is_hidden_by_default_but_shown_with_the_all_toggle(): void
    {
        $user = $this->member('Lemoine');
        $payment = $this->paidMembership($user, []);
        Document::create([
            'user_id' => $user->id, 'category' => 'medical', 'is_current' => true,
            'expiry_date' => now()->addYear(), 'file_path' => 'documents/current.pdf',
            'original_filename' => 'current.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1,
        ]);

        $this->actingAs($this->admin)->get(route('admin.payments.checklist', ['season_year' => '2027']))
            ->assertOk()
            ->assertDontSee('Lemoine');

        $this->actingAs($this->admin)->get(route('admin.payments.checklist', ['season_year' => '2027', 'all' => 1]))
            ->assertOk()
            ->assertSee('Lemoine');

        $this->assertNotNull($payment);
    }

    public function test_a_member_with_no_insurance_component_shows_a_dash_instead_of_a_toggle(): void
    {
        $user = $this->member('Noinsurance');
        $this->paidMembership($user, []);

        $this->actingAs($this->admin)->get(route('admin.payments.checklist', ['season_year' => '2027']))
            ->assertOk()
            ->assertSee('Noinsurance');
    }

    public function test_non_bureau_user_cannot_view_the_checklist(): void
    {
        $member = $this->createMemberUser();

        $this->actingAs($member)->get(route('admin.payments.checklist'))->assertForbidden();
    }
}
