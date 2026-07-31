<?php

namespace Tests\Feature;

use App\Models\MemberDetail;
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
}
