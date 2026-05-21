<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Federation;
use App\Models\MemberDetail;
use App\Models\MemberLicence;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\Role;
use App\Models\User;
use App\Services\FeeCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * @group p0
 */
class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed required lookup tables
        DB::table('roles')->insertOrIgnore(['id' => 2, 'name' => 'member', 'guard_name' => 'web', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'actif']);
        SpatieRole::findOrCreate('member', 'web');
        SpatieRole::findOrCreate('bureau_master', 'web');
        Role::upsert([
            ['id' => 1, 'name' => 'Public', 'slug' => 'public'],
            ['id' => 2, 'name' => 'Member', 'slug' => 'member'],
            ['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master'],
        ], ['id']);
        foreach (['public', 'member', 'bureau_master'] as $r) {
            SpatieRole::findOrCreate($r, 'web');
        }
        MemberStatus::upsert([['id' => 1, 'name' => 'Active', 'slug' => 'active']], ['id']);
    }

    private function createMember(): User
    {
        $u = User::create([
            'username' => fake()->userName(),
            'primary_email' => fake()->unique()->safeEmail(),
            'password' => 'Password1',
            'role_id' => 2,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $u->assignRole('member');
        MemberDetail::create([
            'user_id' => $u->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'phone_mobile' => '+352 621 123 456',
            'date_of_birth' => '1985-03-15',
            'emergency_contact_name' => 'Marie Dupont',
            'emergency_contact_phone' => '+352 621 789 000',
        ]);

        return $u;
    }

    // ── GDPR Export Completeness ──

    /** @test */
    public function gdpr_export_contains_all_personal_data(): void
    {
        $user = $this->createMember();

        // Add licence
        $fed = Federation::create(['acronym' => 'TEST', 'full_name' => 'Test Fed', 'visibility' => 'active']);
        MemberLicence::create(['user_id' => $user->id, 'federation_id' => $fed->id, 'licence_number' => 'T-001', 'season' => '2025-2026']);

        // Add document
        Document::create(['user_id' => $user->id, 'category' => 'medical', 'file_path' => 'test.pdf', 'original_filename' => 'cert.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1024]);

        $response = $this->actingAs($user)->get('/privacy/export');
        $response->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('detail', $data);
        $this->assertArrayHasKey('licences', $data);
        $this->assertArrayHasKey('documents', $data);
        $this->assertArrayHasKey('consents', $data);
        $this->assertArrayHasKey('payments', $data);
        $this->assertNotEmpty($data['licences']);
        $this->assertNotEmpty($data['documents']);
    }

    // ── GDPR Erasure Anonymizes Completely ──

    /** @test */
    public function gdpr_erasure_anonymizes_all_personal_fields(): void
    {
        $user = $this->createMember();
        $userId = $user->id;

        $this->actingAs($user)->post('/privacy/erasure', [
            'confirm' => '1',
            'password' => 'Password1',
        ]);

        $user->refresh();
        $this->assertEquals('ERASED', $user->detail->first_name);
        $this->assertEquals('ERASED', $user->detail->last_name);
        $this->assertNull($user->detail->phone_mobile);
        $this->assertNull($user->detail->date_of_birth);
        $this->assertNull($user->detail->emergency_contact_name);
        $this->assertStringContainsString('erased', $user->primary_email);
        $this->assertNull($user->password);
    }

    /** @test */
    public function gdpr_erasure_deletes_documents(): void
    {
        $user = $this->createMember();
        Document::create(['user_id' => $user->id, 'category' => 'medical', 'file_path' => 'test.pdf', 'original_filename' => 'cert.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1024]);

        $this->actingAs($user)->post('/privacy/erasure', [
            'confirm' => '1',
            'password' => 'Password1',
        ]);

        $this->assertDatabaseMissing('documents', ['user_id' => $user->id, 'deleted_at' => null]);
    }

    // ── Member Delete Cascades ──

    /** @test */
    public function deleting_user_does_not_orphan_registrations(): void
    {
        $user = $this->createMember();
        $event = Event::create([
            'title' => 'Test', 'event_type' => 'social',
            'event_date' => now()->addDays(7), 'status' => 'scheduled',
            'created_by' => $user->id,
        ]);
        EventRegistration::create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'confirmed']);

        // Verify registration exists
        $this->assertDatabaseHas('event_registrations', ['user_id' => $user->id]);

        // After erasure, registrations should still reference the user (anonymized, not deleted)
        $this->actingAs($user)->post('/privacy/erasure', [
            'confirm' => '1',
            'password' => 'Password1',
        ]);

        // User still exists (anonymized), registrations preserved for event history
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('event_registrations', ['user_id' => $user->id]);
    }

    // ── Fee Calculation Determinism ──

    /** @test */
    public function fee_calculation_is_deterministic(): void
    {
        if (! class_exists(FeeCalculationService::class)) {
            $this->markTestSkipped('FeeCalculationService not available');
        }

        $user = $this->createMember();
        $service = app(FeeCalculationService::class);

        // Calculate twice — must be identical
        $result1 = $service->calculate($user, '2025-2026');
        $result2 = $service->calculate($user, '2025-2026');

        $this->assertEquals($result1['amount_due'], $result2['amount_due']);
        $this->assertEquals($result1['communication'], $result2['communication']);
    }

    // ── Licence Preservation During Profile Update ──

    /** @test */
    public function profile_update_does_not_affect_licences(): void
    {
        $user = $this->createMember();
        $fed = Federation::create(['acronym' => 'FFESSM', 'full_name' => 'FFESSM', 'visibility' => 'active']);
        MemberLicence::create([
            'user_id' => $user->id, 'federation_id' => $fed->id,
            'licence_number' => 'A-12345', 'season' => '2025-2026',
            'medical_cert_expiry' => '2026-06-30',
        ]);

        // Update profile (name change)
        $this->actingAs($user)->put('/profile', [
            'first_name' => 'Jean-Pierre',
            'last_name' => 'Dupont',
        ]);

        // Licence must be untouched
        $licence = MemberLicence::where('user_id', $user->id)->first();
        $this->assertEquals('A-12345', $licence->licence_number);
        $this->assertEquals('2025-2026', $licence->season);
        $this->assertEquals('2026-06-30', $licence->medical_cert_expiry->format('Y-m-d'));
    }

    // ── Payment Integrity ──

    /** @test */
    public function paid_payment_cannot_be_deleted_by_cancel(): void
    {
        $user = $this->createMember();
        $event = Event::create([
            'title' => 'Trip', 'event_type' => 'social',
            'event_date' => now()->addDays(14), 'status' => 'scheduled',
            'deposit_1_amount' => 50, 'deposit_1_date' => now()->addDays(7),
            'created_by' => $user->id,
        ]);

        // Register (creates pending payment)
        $this->actingAs($user)->post("/events/{$event->id}/register");

        // Mark payment as paid
        PaymentExpected::where('event_id', $event->id)->where('user_id', $user->id)
            ->update(['status' => 'paid', 'amount_paid' => 50]);

        // Cancel registration
        $this->actingAs($user)->post("/events/{$event->id}/cancel-registration");

        // Paid payment must survive
        $this->assertDatabaseHas('payment_expected', [
            'event_id' => $event->id, 'user_id' => $user->id,
            'status' => 'paid', 'amount_paid' => 50,
        ]);
    }

    // ── Concurrent Registration Safety ──

    /** @test */
    public function double_registration_does_not_create_duplicate(): void
    {
        $user = $this->createMember();
        $event = Event::create([
            'title' => 'Pool', 'event_type' => 'social',
            'event_date' => now()->addDays(7), 'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post("/events/{$event->id}/register");
        $this->actingAs($user)->post("/events/{$event->id}/register");

        $count = EventRegistration::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->count();

        $this->assertEquals(1, $count);
    }
}
