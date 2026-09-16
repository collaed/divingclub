<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class AdminEquipmentTest extends TestCase
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

    public function test_guest_cannot_access_equipment(): void
    {
        $this->get(route('admin.equipment.index'))->assertRedirect(route('login'));
    }

    public function test_bureau_can_list_equipment(): void
    {
        Equipment::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.equipment.index'))
            ->assertOk();
    }

    public function test_bureau_can_create_equipment(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.equipment.store'), [
                'name' => 'Regulator Apex XTX',
                'type' => 'regulator',
                'serial_number' => 'APX-'.uniqid(),
                'status' => 'available',
                'condition' => 'new',
                'is_loanable' => true,
                'location' => 'warehouse',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('equipment', ['name' => 'Regulator Apex XTX']);
    }

    public function test_bureau_can_loan_equipment(): void
    {
        $eq = Equipment::create([
            'name' => 'Loan Test BCD',
            'type' => 'bcd',
            'serial_number' => 'BCD-'.uniqid(),
            'status' => 'available',
            'is_loanable' => true,
        ]);
        $member = $this->createMemberUser();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.equipment.loan', $eq), ['user_id' => $member->id]);

        $response->assertRedirect();
    }

    public function test_loan_form_renders_the_member_select_with_a_hidden_id_field(): void
    {
        $eq = Equipment::create([
            'name' => 'Renders BCD',
            'type' => 'bcd',
            'serial_number' => 'BCD-'.uniqid(),
            'status' => 'available',
            'is_loanable' => true,
        ]);
        $member = $this->createMemberUser();

        $response = $this->actingAs($this->admin)->get(route('admin.equipment.show', $eq))->assertOk();

        $response->assertSee('name="user_id" id="user_id"', false);
        $response->assertSee('<option value="'.$member->name.'" data-id="'.$member->id.'">', false);
    }
}
