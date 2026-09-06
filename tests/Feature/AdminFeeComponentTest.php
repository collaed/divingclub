<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MembershipFeeComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class AdminFeeComponentTest extends TestCase
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

    public function test_store_component_persists_taper_fields(): void
    {
        $this->actingAs($this->admin)->post(route('admin.payments.component.store'), [
            'name' => 'Licence FLASSA',
            'slug' => 'flassa',
            'amount' => 40,
            'is_optional' => '1',
            'taper_below_age' => 18,
            'taper_ratio' => 0,
            'age_anchor_date' => '2027-01-01',
        ])->assertRedirect();

        $c = MembershipFeeComponent::where('slug', 'flassa')->first();
        $this->assertNotNull($c);
        $this->assertSame(18, $c->taper_below_age);
        $this->assertSame('0.000', $c->taper_ratio);
        $this->assertSame('2027-01-01', $c->age_anchor_date->format('Y-m-d'));
    }

    public function test_ajax_update_returns_json_and_persists(): void
    {
        $c = MembershipFeeComponent::create(['name' => 'X', 'slug' => 'x', 'amount' => 10, 'is_optional' => true]);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.payments.component.update', $c), ['taper_ratio' => 0.5, 'taper_below_age' => 16])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $c->refresh();
        $this->assertSame('0.500', $c->taper_ratio);
        $this->assertSame(16, $c->taper_below_age);
    }

    public function test_ajax_update_can_clear_taper_with_empty_string(): void
    {
        $c = MembershipFeeComponent::create(['name' => 'X', 'slug' => 'x', 'amount' => 10, 'taper_below_age' => 18, 'taper_ratio' => 0]);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.payments.component.update', $c), ['taper_below_age' => '', 'taper_ratio' => ''])
            ->assertOk();

        $c->refresh();
        $this->assertNull($c->taper_below_age);
        $this->assertNull($c->taper_ratio);
    }

    public function test_invalid_ratio_rejected(): void
    {
        $c = MembershipFeeComponent::create(['name' => 'X', 'slug' => 'x', 'amount' => 10]);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.payments.component.update', $c), ['taper_ratio' => 2])
            ->assertStatus(422);
    }

    public function test_non_bureau_cannot_update(): void
    {
        $c = MembershipFeeComponent::create(['name' => 'X', 'slug' => 'x', 'amount' => 10]);
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($member)
            ->patchJson(route('admin.payments.component.update', $c), ['taper_ratio' => 0.5])
            ->assertForbidden();
    }
}
