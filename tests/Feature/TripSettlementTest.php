<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MemberDetail;
use App\Models\TripParticipant;
use App\Models\TripReceipt;
use App\Models\User;
use App\Services\TripSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class TripSettlementTest extends TestCase
{
    use RefreshDatabase;

    private TripSettlementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TripSettlementService;
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table($roleTable)->insertOrIgnore(['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
        SpatieRole::findOrCreate('member', 'web');
        SpatieRole::findOrCreate('bureau_master', 'web');
    }

    public function test_empty_trip_returns_zero_balances(): void
    {
        $event = $this->createTripEvent();
        $result = $this->service->calculate($event);

        $this->assertEquals(0, $result['global_pool']);
        $this->assertEquals(0, $result['transit_pool']);
        $this->assertEmpty($result['participants']);
    }

    public function test_global_pool_split_equally(): void
    {
        $event = $this->createTripEvent();
        [$alice, $bob, $carol] = $this->createParticipants($event, 3);

        // Alice paid €150 for groceries
        TripReceipt::create([
            'event_id' => $event->id, 'user_id' => $alice->id,
            'amount' => 150, 'approved_amount' => 150,
            'category' => 'general', 'status' => 'approved',
        ]);

        $result = $this->service->calculate($event);

        $this->assertEquals(150, $result['global_pool']);
        // Each owes €50 global share
        foreach ($result['participants'] as $p) {
            $this->assertEquals(50, $p['global_share']);
        }
        // Alice paid €150, owes €50 → balance = -100 (club owes her)
        $aliceResult = collect($result['participants'])->firstWhere('user_id', $alice->id);
        $this->assertEquals(-100, $aliceResult['balance']);
    }

    public function test_transit_costs_split_among_van_riders(): void
    {
        $event = $this->createTripEvent(['driver_bounty_total' => 0, 'local_daily_charge' => 0]);
        [$alice, $bob, $carol] = $this->createParticipants($event, 3);

        // Alice and Bob are van, Carol flies
        $this->setTransitMode($event, $alice, 'van');
        $this->setTransitMode($event, $bob, 'van');
        $this->setTransitMode($event, $carol, 'fly');

        // Bob paid €400 for fuel+tolls
        TripReceipt::create([
            'event_id' => $event->id, 'user_id' => $bob->id,
            'amount' => 400, 'approved_amount' => 400,
            'category' => 'transit', 'status' => 'approved',
        ]);

        $result = $this->service->calculate($event);

        $this->assertEquals(400, $result['transit_pool']);

        // Transit split between 2 van riders: €200 each
        $aliceResult = collect($result['participants'])->firstWhere('user_id', $alice->id);
        $bobResult = collect($result['participants'])->firstWhere('user_id', $bob->id);
        $carolResult = collect($result['participants'])->firstWhere('user_id', $carol->id);

        $this->assertEquals(200, $aliceResult['transit_share']);
        $this->assertEquals(200, $bobResult['transit_share']);
        $this->assertEquals(0, $carolResult['transit_share']);

        // Bob paid €400, owes €200 transit → balance = -200
        $this->assertEquals(-200, $bobResult['balance']);
        // Alice paid nothing, owes €200 → balance = 200
        $this->assertEquals(200, $aliceResult['balance']);
    }

    public function test_driver_bounty_credits_driver(): void
    {
        $event = $this->createTripEvent(['driver_bounty_total' => 200, 'local_daily_charge' => 0]);
        [$alice, $bob] = $this->createParticipants($event, 2);

        $this->setTransitMode($event, $alice, 'van');
        $this->setTransitMode($event, $bob, 'van');

        // Alice did 100% of the driving
        TripParticipant::where('event_id', $event->id)->where('user_id', $alice->id)
            ->update(['driving_percentage' => 100]);

        // Bob paid €600 for van rental
        TripReceipt::create([
            'event_id' => $event->id, 'user_id' => $bob->id,
            'amount' => 600, 'approved_amount' => 600,
            'category' => 'transit', 'status' => 'approved',
        ]);

        $result = $this->service->calculate($event);

        // Transit pool = €600, bounties = €200, net = €800 / 2 van riders = €400 each
        $this->assertEquals(200, $result['driver_bounties']);

        $aliceResult = collect($result['participants'])->firstWhere('user_id', $alice->id);
        $bobResult = collect($result['participants'])->firstWhere('user_id', $bob->id);

        // Alice: owes €400 transit, gets €200 bounty credit, paid €0 → balance = 200
        $this->assertEquals(200, $aliceResult['bounty_credit']);
        $this->assertEquals(200, $aliceResult['balance']);

        // Bob: owes €400 transit, gets €0 bounty, paid €600 → balance = -200
        $this->assertEquals(-200, $bobResult['balance']);
    }

    public function test_local_daily_charge_subsidizes_van(): void
    {
        $event = $this->createTripEvent(['driver_bounty_total' => 0, 'local_daily_charge' => 15]);
        [$alice, $bob, $carol] = $this->createParticipants($event, 3);

        $this->setTransitMode($event, $alice, 'van');
        $this->setTransitMode($event, $bob, 'van');
        $this->setTransitMode($event, $carol, 'fly');

        // Carol used local van for 5 days
        TripParticipant::where('event_id', $event->id)->where('user_id', $carol->id)
            ->update(['local_transit_days' => 5]);

        // Alice paid €300 for fuel
        TripReceipt::create([
            'event_id' => $event->id, 'user_id' => $alice->id,
            'amount' => 300, 'approved_amount' => 300,
            'category' => 'transit', 'status' => 'approved',
        ]);

        $result = $this->service->calculate($event);

        // Local subsidy = 5 days × €15 = €75
        $this->assertEquals(75, $result['local_subsidy']);

        // Net transit = €300 + 0 bounties - €75 subsidy = €225 / 2 van = €112.50
        $aliceResult = collect($result['participants'])->firstWhere('user_id', $alice->id);
        $carolResult = collect($result['participants'])->firstWhere('user_id', $carol->id);

        $this->assertEquals(112.5, $aliceResult['transit_share']);
        $this->assertEquals(75.0, $carolResult['local_charge']);
    }

    public function test_full_scenario_lux_to_jlp(): void
    {
        // Real-world scenario: 4 people go to Juan-les-Pins
        // 3 in van, 1 flies. Driver did 100% of driving.
        $event = $this->createTripEvent([
            'driver_bounty_total' => 200,
            'local_daily_charge' => 10,
        ]);

        [$driver, $vanA, $vanB, $flyer] = $this->createParticipants($event, 4);

        $this->setTransitMode($event, $driver, 'van');
        $this->setTransitMode($event, $vanA, 'van');
        $this->setTransitMode($event, $vanB, 'van');
        $this->setTransitMode($event, $flyer, 'fly');

        // Driver did 100% of driving
        TripParticipant::where('event_id', $event->id)->where('user_id', $driver->id)
            ->update(['driving_percentage' => 100]);

        // Flyer used local van 7 days
        TripParticipant::where('event_id', $event->id)->where('user_id', $flyer->id)
            ->update(['local_transit_days' => 7]);

        // Receipts: driver paid tolls €120, vanA paid fuel €200, vanB paid groceries €160
        TripReceipt::create(['event_id' => $event->id, 'user_id' => $driver->id, 'amount' => 120, 'approved_amount' => 120, 'category' => 'transit', 'status' => 'approved']);
        TripReceipt::create(['event_id' => $event->id, 'user_id' => $vanA->id, 'amount' => 200, 'approved_amount' => 200, 'category' => 'transit', 'status' => 'approved']);
        TripReceipt::create(['event_id' => $event->id, 'user_id' => $vanB->id, 'amount' => 160, 'approved_amount' => 160, 'category' => 'general', 'status' => 'approved']);

        $result = $this->service->calculate($event);

        // Global pool = €160 / 4 = €40 each
        $this->assertEquals(160, $result['global_pool']);
        $this->assertEquals(40, $result['participants'][0]['global_share']);

        // Transit pool = €320, bounties = €200, local subsidy = 7×10 = €70
        // Net transit = 320 + 200 - 70 = €450 / 3 van riders = €150
        $this->assertEquals(320, $result['transit_pool']);
        $this->assertEquals(200, $result['driver_bounties']);
        $this->assertEquals(70, $result['local_subsidy']);

        // Verify all balances sum to zero (conservation of money)
        $totalBalance = collect($result['participants'])->sum('balance');
        $this->assertEqualsWithDelta(0, $totalBalance, 0.05);
    }

    public function test_pending_receipts_excluded_from_calculation(): void
    {
        $event = $this->createTripEvent();
        [$alice] = $this->createParticipants($event, 1);

        TripReceipt::create(['event_id' => $event->id, 'user_id' => $alice->id, 'amount' => 100, 'approved_amount' => null, 'category' => 'general', 'status' => 'pending']);

        $result = $this->service->calculate($event);
        $this->assertEquals(0, $result['global_pool']);
    }

    public function test_bureau_can_allocate_expense_to_any_participant(): void
    {
        $event = $this->createTripEvent();
        [$alice, $bob] = $this->createParticipants($event, 2);
        $bureau = $this->createBureauUser();

        $response = $this->actingAs($bureau)->post(route('events.settlement.bureau-receipt', $event), [
            'amount' => 50.00,
            'category' => 'general',
            'description' => 'Groceries',
            'user_id' => $bob->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('trip_receipts', [
            'event_id' => $event->id,
            'user_id' => $bob->id,
            'approved_amount' => 50.00,
            'status' => 'approved',
        ]);
    }

    public function test_bureau_cannot_allocate_expense_to_non_participant(): void
    {
        $event = $this->createTripEvent();
        [$alice] = $this->createParticipants($event, 1);
        $bureau = $this->createBureauUser();
        $outsider = $this->createBasicUser();

        $response = $this->actingAs($bureau)->post(route('events.settlement.bureau-receipt', $event), [
            'amount' => 50.00,
            'category' => 'general',
            'description' => 'Groceries',
            'user_id' => $outsider->id,
        ]);

        $response->assertSessionHasErrors('user_id');
    }

    public function test_bureau_can_update_day_rate(): void
    {
        $event = $this->createTripEvent(['local_daily_charge' => 10]);
        $bureau = $this->createBureauUser();

        $response = $this->actingAs($bureau)->post(route('events.settlement.update-day-rate', $event), [
            'local_daily_charge' => 25,
        ]);

        $response->assertRedirect();
        $this->assertEquals(25, $event->fresh()->local_daily_charge);
    }

    public function test_bureau_can_update_day_rate_ajax(): void
    {
        $event = $this->createTripEvent(['local_daily_charge' => 10]);
        $bureau = $this->createBureauUser();

        $response = $this->actingAs($bureau)->post(
            route('events.settlement.update-day-rate', $event),
            ['local_daily_charge' => 18],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertJson(['ok' => true]);
        $this->assertEquals(18, $event->fresh()->local_daily_charge);
    }

    public function test_bureau_can_edit_approved_receipt(): void
    {
        $event = $this->createTripEvent();
        [$alice, $bob] = $this->createParticipants($event, 2);
        $bureau = $this->createBureauUser();

        $receipt = TripReceipt::create([
            'event_id' => $event->id, 'user_id' => $alice->id,
            'amount' => 100, 'approved_amount' => 100,
            'category' => 'transit', 'description' => 'Fuel', 'status' => 'approved',
        ]);

        $response = $this->actingAs($bureau)->put(route('events.settlement.update-receipt', [$event, $receipt]), [
            'amount' => 120,
            'category' => 'general',
            'description' => 'Fuel corrected',
            'user_id' => $bob->id,
        ]);

        $response->assertRedirect();
        $receipt->refresh();
        $this->assertEquals(120, $receipt->approved_amount);
        $this->assertEquals('general', $receipt->category);
        $this->assertEquals($bob->id, $receipt->user_id);
    }

    public function test_bureau_can_delete_receipt(): void
    {
        $event = $this->createTripEvent();
        [$alice] = $this->createParticipants($event, 1);
        $bureau = $this->createBureauUser();

        $receipt = TripReceipt::create([
            'event_id' => $event->id, 'user_id' => $alice->id,
            'amount' => 100, 'approved_amount' => 100,
            'category' => 'general', 'description' => 'test', 'status' => 'approved',
        ]);

        $response = $this->actingAs($bureau)->delete(route('events.settlement.destroy-receipt', [$event, $receipt]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('trip_receipts', ['id' => $receipt->id]);
    }

    public function test_closed_ledger_blocks_expense_operations(): void
    {
        $event = $this->createTripEvent(['settlement_status' => 'closed']);
        [$alice] = $this->createParticipants($event, 1);
        $bureau = $this->createBureauUser();

        // Cannot add
        $this->actingAs($bureau)->post(route('events.settlement.bureau-receipt', $event), [
            'amount' => 50, 'category' => 'general', 'description' => 'X', 'user_id' => $alice->id,
        ])->assertForbidden();

        // Cannot update day rate
        $this->actingAs($bureau)->post(route('events.settlement.update-day-rate', $event), [
            'local_daily_charge' => 99,
        ])->assertForbidden();

        // Cannot delete
        $receipt = TripReceipt::create([
            'event_id' => $event->id, 'user_id' => $alice->id,
            'amount' => 50, 'approved_amount' => 50, 'category' => 'general',
            'description' => 'X', 'status' => 'approved',
        ]);
        $this->actingAs($bureau)->delete(route('events.settlement.destroy-receipt', [$event, $receipt]))
            ->assertForbidden();
    }

    public function test_third_party_expense_not_credited_to_payer(): void
    {
        $event = $this->createTripEvent(['driver_bounty_total' => 0, 'local_daily_charge' => 0]);
        [$alice, $bob] = $this->createParticipants($event, 2);

        // Alice paid €100 normally
        TripReceipt::create([
            'event_id' => $event->id, 'user_id' => $alice->id,
            'amount' => 100, 'approved_amount' => 100,
            'category' => 'general', 'status' => 'approved', 'is_third_party' => false,
        ]);

        // Third-party invoice €200 (assigned to Bob but not really paid by him)
        TripReceipt::create([
            'event_id' => $event->id, 'user_id' => $bob->id,
            'amount' => 200, 'approved_amount' => 200,
            'category' => 'general', 'status' => 'approved', 'is_third_party' => true,
        ]);

        $result = $this->service->calculate($event);

        // Global pool = 100 + 200 = 300, split 2 ways = 150 each
        $this->assertEquals(300, $result['global_pool']);

        $aliceResult = collect($result['participants'])->firstWhere('user_id', $alice->id);
        $bobResult = collect($result['participants'])->firstWhere('user_id', $bob->id);

        // Alice: owes 150, paid 100 → balance = 50
        $this->assertEquals(100.0, $aliceResult['total_paid']);
        $this->assertEquals(50, $aliceResult['balance']);

        // Bob: owes 150, paid 0 (third-party not credited) → balance = 150
        $this->assertEquals(0.0, $bobResult['total_paid']);
        $this->assertEquals(150, $bobResult['balance']);
    }

    public function test_bureau_can_add_third_party_expense(): void
    {
        $event = $this->createTripEvent();
        [$alice] = $this->createParticipants($event, 1);
        $bureau = $this->createBureauUser();

        $response = $this->actingAs($bureau)->post(route('events.settlement.bureau-receipt', $event), [
            'amount' => 300, 'category' => 'general', 'description' => 'Hotel invoice',
            'user_id' => $alice->id, 'is_third_party' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('trip_receipts', [
            'event_id' => $event->id,
            'is_third_party' => true,
            'description' => 'Hotel invoice',
        ]);
    }

    // ─── HELPERS ────────────────────────────────────────────────────────

    private function createTripEvent(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'title' => 'JLP Trip 2026',
            'event_type' => 'long_trip',
            'event_date' => '2026-07-15',
            'end_date' => '2026-07-22',
            'trip_settlement_enabled' => true,
            'driver_bounty_total' => 200,
            'local_daily_charge' => 15,
            'settlement_status' => 'open',
        ], $overrides));
    }

    /** @return User[] */
    private function createParticipants(Event $event, int $count): array
    {
        $users = [];
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        $roleId = DB::table($roleTable)->where('slug', 'member')->value('id') ?? 2;

        for ($i = 0; $i < $count; $i++) {
            $u = User::create([
                'username' => "user$i".uniqid(),
                'primary_email' => "user$i".uniqid().'@test.com',
                'password' => 'Password1',
                'role_id' => $roleId,
                'status_id' => 1,
                'email_verified_at' => now(),
            ]);
            $u->assignRole('member');
            MemberDetail::create(['user_id' => $u->id, 'first_name' => "User$i", 'last_name' => 'Test']);

            TripParticipant::create(['event_id' => $event->id, 'user_id' => $u->id]);
            EventRegistration::create([
                'event_id' => $event->id, 'user_id' => $u->id,
                'status' => 'confirmed', 'transit_mode' => 'van',
            ]);

            $users[] = $u;
        }

        return $users;
    }

    private function setTransitMode(Event $event, User $user, string $mode): void
    {
        EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)
            ->update(['transit_mode' => $mode]);
    }

    private function createBureauUser(): User
    {
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        $roleId = DB::table($roleTable)->where('slug', 'bureau_master')->value('id') ?? 6;

        $u = User::create([
            'username' => 'bureau'.uniqid(),
            'primary_email' => 'bureau'.uniqid().'@test.com',
            'password' => 'Password1',
            'role_id' => $roleId,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $u->assignRole('bureau_master');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Bureau', 'last_name' => 'Admin']);

        return $u;
    }

    private function createBasicUser(): User
    {
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        $roleId = DB::table($roleTable)->where('slug', 'member')->value('id') ?? 2;

        $u = User::create([
            'username' => 'outsider'.uniqid(),
            'primary_email' => 'outsider'.uniqid().'@test.com',
            'password' => 'Password1',
            'role_id' => $roleId,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Outsider', 'last_name' => 'User']);

        return $u;
    }
}
