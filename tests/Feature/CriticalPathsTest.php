<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\MedicalComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * @group p0
 */
class CriticalPathsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function seedRoles(): void
    {
        Role::upsert([
            ['id' => 1, 'name' => 'Public', 'slug' => 'public'],
            ['id' => 2, 'name' => 'Member', 'slug' => 'member'],
            ['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master'],
        ], ['id']);
        // Create Spatie roles
        foreach (['public', 'member', 'instructor', 'bureau_finance', 'bureau_technical', 'bureau_master'] as $r) {
            SpatieRole::findOrCreate($r, 'web');
        }
        MemberStatus::upsert([
            ['id' => 1, 'name' => 'Active', 'slug' => 'active'],
        ], ['id']);
    }

    private function createUser(string $role = 'member', bool $verified = true): User
    {
        $r = Role::where('slug', $role)->first();
        $u = User::create([
            'username' => fake()->userName(),
            'primary_email' => fake()->unique()->safeEmail(),
            'password' => 'Password1',
            'role_id' => $r->id,
            'status_id' => 1,
            'email_verified_at' => $verified ? now() : null,
        ]);
        $u->assignRole($role);
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Test', 'last_name' => 'User']);

        return $u;
    }

    // ── Registration ──

    public function test_guest_can_register(): void
    {
        $this->post('/register', [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'website' => '',
            '_ts' => time() - 5,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['primary_email' => 'jean@example.com']);
    }

    public function test_register_validates_email(): void
    {
        $this->post('/register', [
            'first_name' => 'A', 'last_name' => 'B',
            'email' => 'not-an-email',
            'password' => 'Password1', 'password_confirmation' => 'Password1',
            'website' => '', '_ts' => time() - 5,
        ])->assertSessionHasErrors('email');
    }

    public function test_register_validates_password_min_length(): void
    {
        $this->post('/register', [
            'first_name' => 'A', 'last_name' => 'B',
            'email' => 'a@b.com',
            'password' => 'short', 'password_confirmation' => 'short',
            'website' => '', '_ts' => time() - 5,
        ])->assertSessionHasErrors('password');
    }

    // ── Event Registration & Payment ──

    public function test_user_can_register_for_event(): void
    {
        $user = $this->createUser();
        $event = Event::create([
            'title' => 'Social Night', 'event_type' => 'social',
            'event_date' => now()->addDays(7), 'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post("/events/{$event->id}/register")
            ->assertRedirect();

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id, 'user_id' => $user->id, 'status' => 'confirmed',
        ]);
    }

    public function test_deposit_generates_payment(): void
    {
        $user = $this->createUser();
        $event = Event::create([
            'title' => 'Dive Trip', 'event_type' => 'social',
            'event_date' => now()->addDays(14), 'status' => 'scheduled',
            'deposit_1_amount' => 50, 'deposit_1_date' => now()->addDays(7),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post("/events/{$event->id}/register");

        $this->assertDatabaseHas('payment_expected', [
            'event_id' => $event->id, 'user_id' => $user->id,
            'amount_due' => 50, 'status' => 'pending',
        ]);
    }

    public function test_estimated_cost_does_not_generate_payment(): void
    {
        $user = $this->createUser();
        $event = Event::create([
            'title' => 'Pool Session', 'event_type' => 'pool',
            'event_date' => now()->addDays(7), 'status' => 'scheduled',
            'estimated_cost' => 30, 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post("/events/{$event->id}/register");

        $this->assertDatabaseMissing('payment_expected', ['event_id' => $event->id]);
    }

    public function test_cancel_registration_deletes_pending_payment(): void
    {
        $user = $this->createUser();
        $event = Event::create([
            'title' => 'Trip', 'event_type' => 'social',
            'event_date' => now()->addDays(14), 'status' => 'scheduled',
            'deposit_1_amount' => 75, 'deposit_1_date' => now()->addDays(7),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post("/events/{$event->id}/register");
        $this->actingAs($user)->post("/events/{$event->id}/cancel-registration");

        $this->assertDatabaseMissing('payment_expected', [
            'event_id' => $event->id, 'user_id' => $user->id, 'status' => 'pending',
        ]);
    }

    public function test_can_reregister_after_cancel(): void
    {
        $user = $this->createUser();
        $event = Event::create([
            'title' => 'Pool', 'event_type' => 'social',
            'event_date' => now()->addDays(7), 'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post("/events/{$event->id}/register");
        $this->actingAs($user)->post("/events/{$event->id}/cancel-registration");
        $this->actingAs($user)->post("/events/{$event->id}/register");

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id, 'user_id' => $user->id, 'status' => 'confirmed',
        ]);
    }

    // ── Medical Gate ──

    public function test_medical_gate_blocks_non_compliant_user(): void
    {
        $user = $this->createUser();
        $event = Event::create([
            'title' => 'Dive', 'event_type' => 'dive',
            'event_date' => now()->addDays(7), 'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

        // Mock non-compliant
        $this->mock(MedicalComplianceService::class, function ($mock) {
            $mock->shouldReceive('isCompliant')->andReturn(false);
            $mock->shouldReceive('getStatus')->andReturn(['status' => 'non_compliant', 'badge' => 'danger', 'label' => 'Non-compliant']);
        });

        $this->actingAs($user)->post("/events/{$event->id}/register")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('event_registrations', ['event_id' => $event->id, 'user_id' => $user->id]);
    }

    public function test_social_event_skips_medical_gate(): void
    {
        $user = $this->createUser();
        $event = Event::create([
            'title' => 'BBQ', 'event_type' => 'social',
            'event_date' => now()->addDays(7), 'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post("/events/{$event->id}/register")
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    // ── GDPR ──

    public function test_gdpr_export_returns_json(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->get('/privacy/export')
            ->assertOk()
            ->assertDownload();
    }

    public function test_gdpr_consent_can_be_toggled(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->post('/privacy/consent', [
            'consent_type' => 'photo_publication', 'granted' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('gdpr_consents', [
            'user_id' => $user->id, 'consent_type' => 'photo_publication', 'granted' => true,
        ]);
    }

    // ── Auth ──

    public function test_unverified_user_cannot_access_profile(): void
    {
        $user = $this->createUser('member', false);

        $this->actingAs($user)->get('/profile')
            ->assertRedirect();
    }

    public function test_non_admin_cannot_access_dashboard(): void
    {
        $user = $this->createUser('member');

        $this->actingAs($user)->get('/admin/dashboard')
            ->assertStatus(403);
    }

    public function test_admin_can_access_dashboard(): void
    {
        $user = $this->createUser('bureau_master');

        $this->actingAs($user)->get('/admin/dashboard')
            ->assertOk();
    }

    // ── Public Pages ──

    public function test_public_pages_accessible(): void
    {
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/dues')->assertOk();
    }
}
