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
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
        SpatieRole::findOrCreate('member', 'web');
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
        $event = $this->createTripEvent(['driver_bounty_per_leg' => 0, 'local_daily_charge' => 0]);
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
        $event = $this->createTripEvent(['driver_bounty_per_leg' => 100, 'local_daily_charge' => 0]);
        [$alice, $bob] = $this->createParticipants($event, 2);

        $this->setTransitMode($event, $alice, 'van');
        $this->setTransitMode($event, $bob, 'van');

        // Alice drove 2 legs (Lux→Lyon, Lyon→JLP)
        TripParticipant::where('event_id', $event->id)->where('user_id', $alice->id)
            ->update(['legs_driven' => 2]);

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
        $event = $this->createTripEvent(['driver_bounty_per_leg' => 0, 'local_daily_charge' => 15]);
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
        // 3 in van, 1 flies. 1 driver does both legs.
        $event = $this->createTripEvent([
            'driver_bounty_per_leg' => 50,
            'local_daily_charge' => 10,
        ]);

        [$driver, $vanA, $vanB, $flyer] = $this->createParticipants($event, 4);

        $this->setTransitMode($event, $driver, 'van');
        $this->setTransitMode($event, $vanA, 'van');
        $this->setTransitMode($event, $vanB, 'van');
        $this->setTransitMode($event, $flyer, 'fly');

        // Driver drove both legs (Lux→Lyon, Lyon→JLP)
        TripParticipant::where('event_id', $event->id)->where('user_id', $driver->id)
            ->update(['legs_driven' => 2]);

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

        // Transit pool = €320, bounties = €100, local subsidy = 7×10 = €70
        // Net transit = 320 + 100 - 70 = €350 / 3 van riders = €116.67
        $this->assertEquals(320, $result['transit_pool']);
        $this->assertEquals(100, $result['driver_bounties']);
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

    // ─── HELPERS ────────────────────────────────────────────────────────

    private function createTripEvent(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'title' => 'JLP Trip 2026',
            'event_type' => 'long_trip',
            'event_date' => '2026-07-15',
            'end_date' => '2026-07-22',
            'trip_settlement_enabled' => true,
            'driver_bounty_per_leg' => 100,
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
}
