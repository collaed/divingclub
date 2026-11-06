<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendGearLoanedEmail;
use App\Jobs\SendGearReturnedEmail;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\EventRegistration;
use App\Models\EventWeightLoan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Lending/returning gear from the bureau's quick-entry shorthand
 * ("t14 r22 j36 2w"). All-or-nothing on a bad code — one invalid piece in
 * the batch means nothing is applied, on either side.
 */
class EventGearLoanTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function item(string $type, string $shortNumber): Equipment
    {
        return Equipment::factory()->create(['type' => $type, 'short_number' => $shortNumber, 'is_loanable' => true, 'status' => 'available']);
    }

    private function registration(): EventRegistration
    {
        return EventRegistration::factory()->create(['status' => 'confirmed']);
    }

    public function test_lending_a_mixed_batch_creates_the_right_loans_and_flips_equipment_status(): void
    {
        $bureau = $this->createBureauUser();
        $reg = $this->registration();
        $tank = $this->item('tank', 'T14');
        $regulator = $this->item('regulator', 'R22');
        $bcd = $this->item('bcd', 'J36');

        $this->actingAs($bureau)->postJson(route('events.gear', $reg->event), [
            'registration_id' => $reg->id,
            'text' => 't14 r22 j36 2w',
        ])->assertOk();

        $this->assertSame('on_loan', $tank->fresh()->status);
        $this->assertSame('on_loan', $regulator->fresh()->status);
        $this->assertSame('on_loan', $bcd->fresh()->status);
        $this->assertSame(3, EquipmentLoan::where('event_registration_id', $reg->id)->whereNull('returned_at')->count());
        $weightLoan = EventWeightLoan::where('event_registration_id', $reg->id)->first();
        $this->assertSame(2, $weightLoan->quantity);
    }

    public function test_lending_two_of_the_same_category_creates_two_rows(): void
    {
        $bureau = $this->createBureauUser();
        $reg = $this->registration();
        $this->item('tank', 'T14');
        $this->item('tank', 'T09');

        $this->actingAs($bureau)->postJson(route('events.gear', $reg->event), [
            'registration_id' => $reg->id,
            'text' => 't14 t09',
        ])->assertOk();

        $this->assertSame(2, EquipmentLoan::where('event_registration_id', $reg->id)->count());
    }

    public function test_a_bad_code_makes_the_whole_lend_a_no_op(): void
    {
        $bureau = $this->createBureauUser();
        $reg = $this->registration();
        $tank = $this->item('tank', 'T14');

        $this->actingAs($bureau)->postJson(route('events.gear', $reg->event), [
            'registration_id' => $reg->id,
            'text' => 't14 t66',
        ])->assertStatus(422);

        $this->assertSame('available', $tank->fresh()->status);
        $this->assertSame(0, EquipmentLoan::count());
    }

    public function test_an_already_on_loan_item_makes_the_whole_lend_a_no_op(): void
    {
        $bureau = $this->createBureauUser();
        $reg = $this->registration();
        $otherReg = $this->registration();
        $tank = $this->item('tank', 'T14');
        $regulator = $this->item('regulator', 'R22');
        EquipmentLoan::create(['equipment_id' => $regulator->id, 'user_id' => $otherReg->user_id, 'event_registration_id' => $otherReg->id, 'loaned_at' => now()]);
        $regulator->update(['status' => 'on_loan']);

        $this->actingAs($bureau)->postJson(route('events.gear', $reg->event), [
            'registration_id' => $reg->id,
            'text' => 't14 r22',
        ])->assertStatus(422);

        $this->assertSame('available', $tank->fresh()->status);
        $this->assertSame(0, EquipmentLoan::where('event_registration_id', $reg->id)->count());
    }

    public function test_lending_dispatches_a_summary_email_to_the_member(): void
    {
        Bus::fake();
        $bureau = $this->createBureauUser();
        $reg = $this->registration();
        $this->item('tank', 'T14');

        $this->actingAs($bureau)->postJson(route('events.gear', $reg->event), [
            'registration_id' => $reg->id,
            'text' => 't14',
        ])->assertOk();

        Bus::assertDispatched(SendGearLoanedEmail::class, fn ($job) => $job->userId === $reg->user_id);
    }

    public function test_non_bureau_cannot_lend_gear(): void
    {
        $member = $this->createMemberUser();
        $reg = $this->registration();

        $this->actingAs($member)->postJson(route('events.gear', $reg->event), [
            'registration_id' => $reg->id,
            'text' => 't14',
        ])->assertForbidden();
    }

    public function test_returning_a_mixed_batch_closes_loans_and_consumes_weights(): void
    {
        Bus::fake();
        $bureau = $this->createBureauUser();
        $reg = $this->registration();
        $tank = $this->item('tank', 'T01');
        $regulator = $this->item('regulator', 'R21');
        EquipmentLoan::create(['equipment_id' => $tank->id, 'user_id' => $reg->user_id, 'event_registration_id' => $reg->id, 'loaned_at' => now()]);
        EquipmentLoan::create(['equipment_id' => $regulator->id, 'user_id' => $reg->user_id, 'event_registration_id' => $reg->id, 'loaned_at' => now()]);
        $tank->update(['status' => 'on_loan']);
        $regulator->update(['status' => 'on_loan']);
        $weightLoan = EventWeightLoan::create(['event_id' => $reg->event_id, 'event_registration_id' => $reg->id, 'quantity' => 7, 'loaned_at' => now()]);

        $this->actingAs($bureau)->post(route('admin.equipment.batch-return'), ['text' => 't01 r21 7w'])
            ->assertRedirect();

        $this->assertSame('available', $tank->fresh()->status);
        $this->assertSame('available', $regulator->fresh()->status);
        $this->assertNotNull(EquipmentLoan::where('equipment_id', $tank->id)->first()->returned_at);
        $this->assertSame(7, $weightLoan->fresh()->quantity_returned);
        Bus::assertDispatched(SendGearReturnedEmail::class);
    }

    public function test_partial_weight_return_leaves_the_remainder_outstanding(): void
    {
        $bureau = $this->createBureauUser();
        $reg = $this->registration();
        $weightLoan = EventWeightLoan::create(['event_id' => $reg->event_id, 'event_registration_id' => $reg->id, 'quantity' => 7, 'loaned_at' => now()]);

        $this->actingAs($bureau)->post(route('admin.equipment.batch-return'), ['text' => '3w'])->assertRedirect();

        $weightLoan->refresh();
        $this->assertSame(3, $weightLoan->quantity_returned);
        $this->assertNull($weightLoan->returned_at);
        $this->assertSame(4, $weightLoan->outstanding());
    }

    public function test_returning_weights_while_two_events_have_them_out_is_rejected(): void
    {
        $bureau = $this->createBureauUser();
        $regA = $this->registration();
        $regB = $this->registration();
        EventWeightLoan::create(['event_id' => $regA->event_id, 'event_registration_id' => $regA->id, 'quantity' => 2, 'loaned_at' => now()]);
        EventWeightLoan::create(['event_id' => $regB->event_id, 'event_registration_id' => $regB->id, 'quantity' => 3, 'loaned_at' => now()]);

        $this->actingAs($bureau)->post(route('admin.equipment.batch-return'), ['text' => '2w'])
            ->assertRedirect()->assertSessionHasErrors('text');

        $this->assertSame(0, EventWeightLoan::sum('quantity_returned'));
    }

    public function test_a_bad_return_code_applies_nothing(): void
    {
        $bureau = $this->createBureauUser();
        $reg = $this->registration();
        $tank = $this->item('tank', 'T01');
        EquipmentLoan::create(['equipment_id' => $tank->id, 'user_id' => $reg->user_id, 'event_registration_id' => $reg->id, 'loaned_at' => now()]);
        $tank->update(['status' => 'on_loan']);

        $this->actingAs($bureau)->post(route('admin.equipment.batch-return'), ['text' => 't01 t99'])
            ->assertRedirect()->assertSessionHasErrors('text');

        $this->assertSame('on_loan', $tank->fresh()->status);
        $this->assertNull(EquipmentLoan::where('equipment_id', $tank->id)->first()->returned_at);
    }

    public function test_non_bureau_cannot_batch_return(): void
    {
        $member = $this->createMemberUser();

        $this->actingAs($member)->post(route('admin.equipment.batch-return'), ['text' => 't01'])
            ->assertForbidden();
    }

    public function test_cancelling_a_registration_does_not_auto_return_its_gear(): void
    {
        $bureau = $this->createBureauUser();
        $reg = $this->registration();
        $tank = $this->item('tank', 'T14');
        EquipmentLoan::create(['equipment_id' => $tank->id, 'user_id' => $reg->user_id, 'event_registration_id' => $reg->id, 'loaned_at' => now()]);
        $tank->update(['status' => 'on_loan']);

        $this->actingAs($bureau)->post(route('events.cancel-registration', $reg->event), ['user_id' => $reg->user_id]);

        $this->assertSame('on_loan', $tank->fresh()->status);
        $this->assertNull(EquipmentLoan::where('equipment_id', $tank->id)->first()->returned_at);
    }
}
