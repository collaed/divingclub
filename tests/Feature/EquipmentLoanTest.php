<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

#[Group('p1')]
class EquipmentLoanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::upsert([['id' => 2, 'name' => 'Member', 'slug' => 'member'], ['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master']], ['id']);
        foreach (['member', 'bureau_master'] as $r) {
            SpatieRole::findOrCreate($r, 'web');
        }
        MemberStatus::upsert([['id' => 1, 'name' => 'Active', 'slug' => 'active']], ['id']);
    }

    private function admin(): User
    {
        $u = User::create(['primary_email' => fake()->unique()->safeEmail(), 'password' => 'P', 'role_id' => 6, 'status_id' => 1, 'email_verified_at' => now()]);
        $u->assignRole('bureau_master');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'A', 'last_name' => 'D']);

        return $u;
    }

    private function member(): User
    {
        $u = User::create(['primary_email' => fake()->unique()->safeEmail(), 'password' => 'P', 'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now()]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'M', 'last_name' => 'E']);

        return $u;
    }

    public function test_loan_equipment(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $eq = Equipment::create(['name' => 'BCD', 'type' => 'bcd', 'short_number' => 'B01', 'status' => 'available']);

        $this->actingAs($admin)->post("/admin/equipment/{$eq->id}/loan", ['user_id' => $member->id]);

        $eq->refresh();
        $this->assertEquals('on_loan', $eq->status);
        $this->assertDatabaseHas('equipment_loans', ['equipment_id' => $eq->id, 'user_id' => $member->id]);
    }

    public function test_cannot_loan_unavailable_equipment(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $eq = Equipment::create(['name' => 'BCD', 'type' => 'bcd', 'short_number' => 'B02', 'status' => 'on_loan']);

        $this->actingAs($admin)->post("/admin/equipment/{$eq->id}/loan", ['user_id' => $member->id])->assertSessionHas('error');
    }

    public function test_return_sets_available(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $eq = Equipment::create(['name' => 'BCD', 'type' => 'bcd', 'short_number' => 'B03', 'status' => 'on_loan']);
        $loan = EquipmentLoan::create(['equipment_id' => $eq->id, 'user_id' => $member->id, 'loaned_at' => now(), 'loaned_by' => $admin->id]);

        $this->actingAs($admin)->post("/admin/equipment/return/{$loan->id}");

        $eq->refresh();
        $loan->refresh();
        $this->assertNotNull($loan->returned_at);
        $this->assertEquals('available', $eq->status);
    }
}
