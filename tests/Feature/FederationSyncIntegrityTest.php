<?php

namespace Tests\Feature;

use App\Models\ClubPartnership;
use App\Models\Event;
use App\Models\ExternalRegistration;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * Tests cross-database compatibility and data integrity for:
 * - Federation API ingestion (external registrations)
 * - Date parsing edge cases (null, zero-dates, malformed)
 * - Type coercion (strings in integer columns, null handling)
 * - Sync data transformation (legacy format → new schema)
 */
class FederationSyncIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
        SpatieRole::findOrCreate('member', 'web');
        SpatieRole::findOrCreate('bureau_master', 'web');
    }

    // ─── DATE PARSING (mirrors LegacySyncBidirectional helpers) ──────────

    public function test_parse_date_handles_null_and_zero_dates(): void
    {
        // These are the exact edge cases from Joomla's MySQL data
        $this->assertNull($this->parseDate(null));
        $this->assertNull($this->parseDate(''));
        $this->assertNull($this->parseDate('0000-00-00'));
        $this->assertNull($this->parseDate('0000-00-00 00:00:00'));
    }

    public function test_parse_date_handles_valid_dates(): void
    {
        $this->assertEquals('2026-05-23', $this->parseDate('2026-05-23'));
        $this->assertEquals('1985-12-01', $this->parseDate('1985-12-01'));
        $this->assertEquals('2026-01-15', $this->parseDate('2026-01-15 14:30:00'));
    }

    public function test_parse_date_handles_malformed_input(): void
    {
        $this->assertNull($this->parseDate('not-a-date'));
        $this->assertNull($this->parseDate('32/13/2026'));
        $this->assertNull($this->parseDate('abc'));
    }

    public function test_parse_legacy_date_dd_mm_yy_format(): void
    {
        $this->assertEquals('2024-03-15', $this->parseLegacyDate('15/03/24'));
        $this->assertEquals('2026-12-01', $this->parseLegacyDate('01/12/26'));
        $this->assertNull($this->parseLegacyDate('invalid'));
        $this->assertNull($this->parseLegacyDate('32/13/24')); // invalid month
        $this->assertNull($this->parseLegacyDate(''));
    }

    // ─── FEDERATION API: TYPE COERCION & BOUNDARY VALUES ────────────────

    public function test_federation_register_stores_dates_correctly(): void
    {
        $partner = $this->createPartner();
        $event = $this->createFederatedEvent();

        $response = $this->postJson('/api/federation/register', [
            'event_id' => $event->id,
            'member_name' => 'Hans Müller',
            'member_email' => 'hans@example.de',
            'cert_level' => 'CMAS 2★',
            'medical_valid_until' => '2027-03-15',
        ], $this->apiHeaders($partner));

        $response->assertCreated();

        $reg = ExternalRegistration::first();
        $this->assertNotNull($reg);
        $this->assertEquals('2027-03-15', $reg->external_medical_valid_until->format('Y-m-d'));
        $this->assertEquals('Hans Müller', $reg->external_member_name);
        $this->assertEquals('CMAS 2★', $reg->external_cert_level);
    }

    public function test_federation_register_handles_null_optional_dates(): void
    {
        $partner = $this->createPartner();
        $event = $this->createFederatedEvent();

        $response = $this->postJson('/api/federation/register', [
            'event_id' => $event->id,
            'member_name' => 'No Medical',
            'medical_valid_until' => null,
        ], $this->apiHeaders($partner));

        $response->assertCreated();

        $reg = ExternalRegistration::first();
        $this->assertNull($reg->external_medical_valid_until);
    }

    public function test_federation_register_rejects_invalid_date_format(): void
    {
        $partner = $this->createPartner();
        $event = $this->createFederatedEvent();

        $response = $this->postJson('/api/federation/register', [
            'event_id' => $event->id,
            'member_name' => 'Bad Date',
            'medical_valid_until' => 'not-a-date',
        ], $this->apiHeaders($partner));

        $response->assertUnprocessable();
    }

    public function test_federation_register_handles_unicode_names(): void
    {
        $partner = $this->createPartner();
        $event = $this->createFederatedEvent();

        $names = ['José García', 'Ólafur Björnsson', 'Müller-Lüdenscheid', '田中太郎'];

        foreach ($names as $name) {
            $response = $this->postJson('/api/federation/register', [
                'event_id' => $event->id,
                'member_name' => $name,
            ], $this->apiHeaders($partner));

            $response->assertCreated();
        }

        $this->assertEquals(4, ExternalRegistration::count());
        $this->assertEquals('José García', ExternalRegistration::first()->external_member_name);
    }

    public function test_federation_register_enforces_slot_limit(): void
    {
        $partner = $this->createPartner();
        $event = $this->createFederatedEvent(['external_slots' => 2]);

        // Fill both slots
        for ($i = 1; $i <= 2; $i++) {
            $this->postJson('/api/federation/register', [
                'event_id' => $event->id,
                'member_name' => "Member $i",
            ], $this->apiHeaders($partner))->assertCreated();
        }

        // Third should be rejected
        $response = $this->postJson('/api/federation/register', [
            'event_id' => $event->id,
            'member_name' => 'Member 3',
        ], $this->apiHeaders($partner));

        $response->assertStatus(409);
    }

    // ─── MEMBER DETAIL: CROSS-DB TYPE SAFETY ────────────────────────────

    public function test_member_detail_stores_cotisation_years_as_json(): void
    {
        $user = $this->createUser();
        $detail = $user->detail;

        // Simulate what the sync does: array of year integers
        $detail->update(['cotisation_years' => [2023, 2024, 2025]]);

        $fresh = MemberDetail::find($detail->id);
        $this->assertIsArray($fresh->cotisation_years);
        $this->assertEquals([2023, 2024, 2025], $fresh->cotisation_years);
    }

    public function test_member_detail_handles_empty_cotisation_years(): void
    {
        $user = $this->createUser();
        $detail = $user->detail;

        $detail->update(['cotisation_years' => []]);

        $fresh = MemberDetail::find($detail->id);
        $this->assertIsArray($fresh->cotisation_years);
        $this->assertEmpty($fresh->cotisation_years);
    }

    public function test_member_detail_date_of_birth_stored_correctly(): void
    {
        $user = $this->createUser();
        $detail = $user->detail;

        $detail->update(['date_of_birth' => '1990-06-15']);

        $fresh = MemberDetail::find($detail->id);
        $this->assertEquals('1990-06-15', Carbon::parse($fresh->date_of_birth)->format('Y-m-d'));
    }

    public function test_member_detail_null_date_of_birth(): void
    {
        $user = $this->createUser();
        $detail = $user->detail;

        $detail->update(['date_of_birth' => null]);

        $fresh = MemberDetail::find($detail->id);
        $this->assertNull($fresh->date_of_birth);
    }

    // ─── HELPERS (mirror the sync command's private methods) ─────────────

    private function parseDate(?string $value): ?string
    {
        if (! $value || $value === '0000-00-00' || str_starts_with($value, '0000')) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseLegacyDate(string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $parts = explode('/', $value);
        if (count($parts) !== 3) {
            return null;
        }

        $day = (int) $parts[0];
        $month = (int) $parts[1];
        $year = (int) $parts[2];
        $year = $year < 100 ? 2000 + $year : $year;

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function createPartner(): ClubPartnership
    {
        return ClubPartnership::create([
            'name' => 'Partner Club',
            'base_url' => 'https://partner.example.com',
            'api_key_id' => 'test-key-123',
            'api_secret_hash' => Hash::make('test-secret'),
            'is_active' => true,
        ]);
    }

    private function createFederatedEvent(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'title' => 'Federated Dive Trip',
            'event_date' => now()->addMonth()->format('Y-m-d'),
            'event_type' => 'long_trip',
            'is_federated' => true,
            'external_slots' => 5,
            'status' => 'published',
        ], $overrides));
    }

    private function apiHeaders(ClubPartnership $partner): array
    {
        return [
            'X-Club-Key-Id' => $partner->api_key_id,
            'X-Club-Secret' => 'test-secret',
        ];
    }

    private function createUser(): User
    {
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        $roleId = DB::table($roleTable)->where('slug', 'member')->value('id') ?? 2;

        $u = User::create([
            'username' => fake()->userName(),
            'primary_email' => fake()->unique()->safeEmail(),
            'password' => 'Password1',
            'role_id' => $roleId,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Test', 'last_name' => 'User']);

        return $u;
    }
}
